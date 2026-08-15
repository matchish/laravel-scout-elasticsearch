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
     * A live change: the row as it is right now.
     */
    const WRITER_LIVE = 'live';

    /**
     * A snapshot taken by an import, which may already be stale by
     * the time it reaches Elasticsearch.
     */
    const WRITER_SNAPSHOT = 'snapshot';

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
     * @param  string  $writer  self::WRITER_LIVE or self::WRITER_SNAPSHOT — decides which
     *                          write wins when both touch a document at the same moment
     */
    public function __construct(?string $index = null, bool $requireAlias = false, string $writer = self::WRITER_LIVE)
    {
        $this->index = $index;
        $this->requireAlias = $requireAlias;
        $this->writer = $writer;
    }

    /**
     * The version a write carries, as a (timestamp, writer) pair packed
     * into one integer: the row's updated_at doubled, plus one for a
     * live write. A live change therefore outranks a snapshot taken in
     * the same second, so the two orderings of the same pair of writes
     * reach the same result.
     *
     * Null when the model keeps no updated_at, which leaves the write
     * unversioned and preserves the previous behaviour.
     *
     * @param  Model  $model
     */
    private function version($model): ?int
    {
        if (! $model->usesTimestamps()) {
            return null;
        }

        $column = $model->getUpdatedAtColumn();
        if ($column === null) {
            return null;
        }

        $updatedAt = $model->getAttribute($column);
        if (! $updatedAt instanceof \DateTimeInterface) {
            return null;
        }

        return ((int) $updatedAt->format('U')) * 2 + ($this->writer === self::WRITER_LIVE ? 1 : 0);
    }

    /**
     * Snapshot writes must lose to anything already stored at the same
     * version or later; live writes may overwrite an equal version,
     * because the pairing above already put them ahead of a snapshot
     * from the same second.
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
                $version = $this->version($model);
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
                // A delete is always a live change, so it outranks any
                // snapshot of the row — including one taken in the same
                // second, which is what stops a straggling import job
                // from resurrecting a deleted document.
                $version = $this->version($model);
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
