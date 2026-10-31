<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;

/**
 * The durable state of one parallel import: which ranges have written
 * every row, which jobs have handed their range on, whether the
 * finishing job has started, and why it failed if it did. The finishing
 * job removes the state as its last step, so the state being gone means
 * the import is published.
 *
 * Any queue may deliver a job twice, and the batch counts deliveries:
 * a repeat can bring its pending count to zero while ranges still run,
 * or bring it back to zero more than once. Every record here is written
 * under a fixed id, so writing it twice changes nothing, and decisions
 * that must happen once use create-if-absent.
 *
 * The records live in a small Elasticsearch index next to the import
 * index, and are written and read only by id. A read by id sees every
 * confirmed write at once; search would see a write only after the
 * next refresh. Writes go through an alias with require_alias, so a
 * late write after the state is removed is rejected instead of
 * creating the index again.
 *
 * @internal
 */
final class ImportState
{
    private const IDS_PER_REQUEST = 1000;

    /**
     * @var string
     */
    private $index;

    /**
     * @var string
     */
    private $alias;

    /**
     * @var int
     */
    private $ranges;

    private function __construct(string $index, string $alias, int $ranges)
    {
        $this->index = $index;
        $this->alias = $alias;
        $this->ranges = $ranges;
    }

    /**
     * The state of the import that writes to $importIndex and planned
     * $ranges ranges. The names share the import index's prefix, so
     * Elasticsearch permissions granted for one cover the other.
     */
    public static function forImport(string $importIndex, int $ranges): self
    {
        $alias = $importIndex.'-state';

        return new self($alias.'-0', $alias, $ranges);
    }

    /**
     * The alias every read and write goes through.
     */
    public function alias(): string
    {
        return $this->alias;
    }

    public function create(Client $elasticsearch): void
    {
        $elasticsearch->indices()->create([
            'index' => $this->index,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'auto_expand_replicas' => '0-1',
                    // A confirmed record must survive a crash, whatever
                    // the application chose for its own indices.
                    'index.translog.durability' => 'request',
                ],
                'mappings' => [
                    'dynamic' => false,
                    // Lets CleanUp reclaim the state of an import that died.
                    '_meta' => ['scout_import' => true],
                ],
                'aliases' => [$this->alias => new \stdClass()],
            ],
        ]);
    }

    public function exists(Client $elasticsearch): bool
    {
        /** @var Elasticsearch $response */
        $response = $elasticsearch->indices()->exists(['index' => $this->alias]);

        return $response->asBool();
    }

    public function recordFinished(Client $elasticsearch, int $range): void
    {
        $this->write($elasticsearch, 'range-'.$range, false);
    }

    /**
     * Whether every planned range has recorded that it is finished.
     * False once the state is removed: the import was published, or a
     * newer import replaced it.
     */
    public function complete(Client $elasticsearch): bool
    {
        if ($this->ranges === 0) {
            return $this->exists($elasticsearch);
        }

        // Every range job asks this when it finishes. Ranges finish
        // roughly in the order they were queued, so the newest ranges
        // are the likeliest to be missing: checking them first lets most
        // calls stop after one request.
        $last = $this->ranges - 1;
        for ($first = $last - ($last % self::IDS_PER_REQUEST); $first >= 0; $first -= self::IDS_PER_REQUEST) {
            $ids = array_map(function (int $range) {
                return 'range-'.$range;
            }, range($first, min($last, $first + self::IDS_PER_REQUEST - 1)));

            try {
                /** @var Elasticsearch $response */
                $response = $elasticsearch->mget([
                    'index' => $this->alias,
                    '_source' => false,
                    'body' => ['ids' => $ids],
                ]);
            } catch (ClientResponseException $e) {
                return false;
            }

            $docs = $response->asArray()['docs'] ?? null;
            if (! is_array($docs) || count($docs) !== count($ids)) {
                return false;
            }
            foreach ($docs as $doc) {
                if (! is_array($doc) || ($doc['found'] ?? false) !== true) {
                    return false;
                }
            }
        }

        return true;
    }

    public function handedOff(Client $elasticsearch, string $job): bool
    {
        /** @var Elasticsearch $response */
        $response = $elasticsearch->exists(['index' => $this->alias, 'id' => 'handoff-'.$job]);

        return $response->asBool();
    }

    public function recordHandOff(Client $elasticsearch, string $job): void
    {
        $this->write($elasticsearch, 'handoff-'.$job, false);
    }

    /**
     * True for the one job that may start finishing the import, which
     * $job names. Another delivery of that same job gets true again, so
     * a crash between the claim and the start cannot lose the import;
     * finishing twice is safe. False for every other job, and once the
     * state is gone.
     */
    public function claimFinish(Client $elasticsearch, string $job): bool
    {
        if ($this->write($elasticsearch, 'finish', true, ['job' => $job])) {
            return true;
        }

        try {
            /** @var Elasticsearch $response */
            $response = $elasticsearch->get(['index' => $this->alias, 'id' => 'finish']);
        } catch (ClientResponseException $e) {
            return false;
        }

        return ($response->asArray()['_source']['job'] ?? null) === $job;
    }

    /**
     * Records why the import could not be published, for the console
     * that waits for it.
     */
    public function recordFailure(Client $elasticsearch, string $reason): void
    {
        $this->write($elasticsearch, 'failure', false, ['reason' => $reason]);
    }

    public function failure(Client $elasticsearch): ?string
    {
        try {
            /** @var Elasticsearch $response */
            $response = $elasticsearch->get(['index' => $this->alias, 'id' => 'failure']);
        } catch (ClientResponseException $e) {
            return null;
        }
        $reason = $response->asArray()['_source']['reason'] ?? null;

        return is_string($reason) ? $reason : 'unknown reason';
    }

    public function delete(Client $elasticsearch): void
    {
        try {
            $elasticsearch->indices()->delete(['index' => $this->index]);
        } catch (ClientResponseException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return bool whether this call wrote the record
     */
    private function write(Client $elasticsearch, string $id, bool $onlyIfAbsent, array $body = []): bool
    {
        try {
            $elasticsearch->index([
                'index' => $this->alias,
                'id' => $id,
                'op_type' => $onlyIfAbsent ? 'create' : 'index',
                'require_alias' => true,
                'body' => $body + ['at' => time()],
            ]);

            return true;
        } catch (ClientResponseException $e) {
            // 409: the record exists already. 404: the state was removed,
            // so the import is over and there is nothing left to record.
            if (in_array($e->getCode(), [404, 409], true)) {
                return false;
            }
            throw $e;
        }
    }
}
