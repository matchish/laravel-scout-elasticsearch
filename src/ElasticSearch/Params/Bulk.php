<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Params;

use Illuminate\Database\Eloquent\Model;
use Matchish\ScoutElasticSearch\Contracts\SearchableContract;

/**
 * @internal
 *
 * @phpstan-import-type SearchableModel from SearchableContract
 */
final class Bulk
{
    /**
     * A change happening right now: Scout's observers, and anything
     * else that goes through the engine.
     */
    const WRITER_LIVE = 'live';

    /**
     * Rows an import read earlier and writes now, which may already be
     * stale by the time they reach Elasticsearch.
     */
    const WRITER_SNAPSHOT = 'snapshot';

    /**
     * Rows the catch-up pass re-read after every snapshot was written.
     * Still a snapshot, but a later one.
     */
    const WRITER_CATCH_UP = 'catch-up';

    /**
     * Writers in order of how fresh their data can be at the same
     * second. A write from a later rank wins a tie.
     */
    private const RANKS = [
        self::WRITER_SNAPSHOT => 0,
        self::WRITER_CATCH_UP => 1,
        self::WRITER_LIVE => 2,
    ];

    /**
     * @var array<string|int, Model>
     */
    private $indexDocs = [];

    /**
     * @var array<string|int, Model>
     */
    private $deleteDocs = [];

    /**
     * @var string|null
     */
    private $index;

    /**
     * @var bool
     */
    private $requireAlias;

    /**
     * @var string
     */
    private $writer;

    /**
     * @param  string|null  $index  write target; defaults to the model's searchableAs() alias
     * @param  bool  $requireAlias  reject the write unless the target is an alias, so a
     *                              revoked import can never auto-create its old index
     * @param  string  $writer  one of the WRITER_* constants — decides which write wins
     *                          when two writers touch a document at the same moment
     */
    public function __construct(?string $index = null, bool $requireAlias = false, string $writer = self::WRITER_LIVE)
    {
        if (! isset(self::RANKS[$writer])) {
            throw new \InvalidArgumentException("Unknown writer [$writer].");
        }

        $this->index = $index;
        $this->requireAlias = $requireAlias;
        $this->writer = $writer;
    }

    /**
     * The version a write carries: a (time, rank) pair packed into one
     * integer, so Elasticsearch keeps whichever write holds the newest
     * data, whatever order the writes arrive in.
     *
     * The time is when the data in the write became true:
     * - an index write carries the row as of its updated_at;
     * - a live delete becomes true now, as it is sent — so it beats
     *   every copy read before it, even when it is sent from a model
     *   instance loaded before someone else edited the row;
     * - any other delete records a row that stopped being searchable,
     *   which became true at its last change or its deletion.
     * Every time is capped at now, so a clock running ahead cannot let
     * old data claim to be from the future.
     *
     * Null when the model keeps no updated_at: its snapshots could not
     * be ordered against live writes, so the write stays unversioned,
     * as before.
     *
     * @param  Model  $model
     */
    private function version($model, bool $delete): ?int
    {
        if (! $model->usesTimestamps()) {
            return null;
        }

        $column = $model->getUpdatedAtColumn();
        if ($column === null) {
            return null;
        }

        $now = $model->freshTimestamp();
        if ($delete && $this->writer === self::WRITER_LIVE) {
            $time = $now;
        } else {
            $time = $this->latest([
                $model->getAttribute($column),
                $delete && method_exists($model, 'getDeletedAtColumn')
                    ? $model->getAttribute($model->getDeletedAtColumn())
                    : null,
            ]);
            if ($time === null) {
                return null;
            }
            if ($time > $now) {
                $time = $now;
            }
        }

        return ((int) $time->format('U')) * count(self::RANKS) + self::RANKS[$this->writer];
    }

    /**
     * @param  array<int, mixed>  $times
     */
    private function latest(array $times): ?\DateTimeInterface
    {
        $latest = null;
        foreach ($times as $time) {
            if ($time instanceof \DateTimeInterface && ($latest === null || $time > $latest)) {
                $latest = $time;
            }
        }

        return $latest;
    }

    /**
     * Snapshots must lose to anything already stored at the same version
     * or later. Live writes may replace an equal version, so a second
     * change within the same second still lands.
     */
    private function versionType(): string
    {
        return $this->writer === self::WRITER_LIVE ? 'external_gte' : 'external';
    }

    /**
     * @param  array<Model>|object  $docs
     */
    public function delete($docs): void
    {
        if (is_iterable($docs)) {
            foreach ($docs as $doc) {
                $this->delete($doc);
            }
        } else {
            /** @var SearchableModel $docs */
            $this->deleteDocs[$docs->getScoutKey()] = $docs;
        }
    }

    /**
     * TODO: Add ability to extend payload without modifying the class.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $payload = ['body' => []];
        if ($this->requireAlias) {
            $payload['require_alias'] = true;
        }
        $payload = collect($this->indexDocs)->reduce(
            function ($payload, $model) {
                /** @var SearchableModel $model */
                if (config('scout.soft_delete', false) && $model::usesSoftDelete()) {
                    $model->pushSoftDeleteMetadata();
                }

                $attributes = $model->getAttributes();
                $routing = $attributes['routing'] ?? null;
                $scoutKey = $model->getScoutKey();

                $action = [
                    '_index' => $this->index ?? $model->searchableAs(),
                    '_id' => $scoutKey,
                    'routing' => false === empty($routing) ? $routing : $scoutKey,
                ];
                $version = $this->version($model, false);
                if ($version !== null) {
                    $action['version'] = $version;
                    $action['version_type'] = $this->versionType();
                }
                $payload['body'][] = ['index' => $action];

                $payload['body'][] = array_merge(
                    $model->toSearchableArray(),
                    $model->scoutMetadata(),
                    [
                        '__class_name' => get_class($model),
                    ]
                );

                return $payload;
            }, $payload);

        $payload = collect($this->deleteDocs)->reduce(
            function ($payload, $model) {
                /** @var SearchableModel $model */
                $attributes = $model->getAttributes();
                $routing = $attributes['routing'] ?? null;
                $scoutKey = $model->getScoutKey();

                $action = [
                    '_index' => $this->index ?? $model->searchableAs(),
                    '_id' => $scoutKey,
                    'routing' => false === empty($routing) ? $routing : $scoutKey,
                ];
                // A delete is versioned like any other write. A live delete
                // carries the current time, so it beats every copy of the
                // row read before it: a straggling import job cannot bring
                // the document back, and a delete sent from a stale model
                // instance is not refused because someone edited the row
                // after the instance was loaded.
                $version = $this->version($model, true);
                if ($version !== null) {
                    $action['version'] = $version;
                    $action['version_type'] = $this->versionType();
                }
                $payload['body'][] = ['delete' => $action];

                return $payload;
            }, $payload);

        /** @var array<mixed> */
        return $payload;
    }

    /**
     * @param  array<Model>|object  $docs
     */
    public function index($docs): void
    {
        if (is_iterable($docs)) {
            foreach ($docs as $doc) {
                $this->index($doc);
            }
        } else {
            /** @var SearchableModel $docs */
            $this->indexDocs[$docs->getScoutKey()] = $docs;
        }
    }
}
