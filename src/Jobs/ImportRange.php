<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Matchish\ScoutElasticSearch\ElasticSearch\BulkResult;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
use Matchish\ScoutElasticSearch\Searchable\Partitionable;
use Matchish\ScoutElasticSearch\Searchable\Range;

/**
 * Imports one key range of an import source into a concrete index.
 *
 * The job pages through its range with keyset pagination and bulk
 * indexes every chunk. It writes to the concrete index name instead
 * of the write alias, so a job from a superseded import can never
 * pollute the index of a newer import.
 *
 * Inside a batch, one job processes at most $maxChunks chunks and then
 * hands the rest of its range to a new job in the same batch. A range
 * can hold any number of rows — many rows can share one partition
 * value, or one outlier key can stretch the key space — but no job
 * runs long enough to outlive the queue's retry_after or --timeout.
 *
 * @internal
 */
final class ImportRange implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var Partitionable
     */
    private $source;

    /**
     * @var Range
     */
    private $range;

    /**
     * @var string
     */
    private $column;

    /**
     * @var string
     */
    private $indexName;

    /**
     * @var int
     */
    private $chunkSize;

    /**
     * @var int
     */
    private $maxChunks;

    /**
     * @var array{0: mixed, 1: mixed}|null
     */
    private $after;

    /**
     * @param  Partitionable  $source
     * @param  Range  $range
     * @param  string  $column  partition column, unqualified
     * @param  string  $indexName  concrete index name to write to
     * @param  int  $chunkSize
     * @param  int  $maxChunks  chunks one job processes before it hands on the rest; 0 for no limit
     * @param  array{0: mixed, 1: mixed}|null  $after  (partition, key) of the last row a previous job wrote
     */
    public function __construct(
        Partitionable $source,
        Range $range,
        string $column,
        string $indexName,
        int $chunkSize,
        int $maxChunks = 0,
        ?array $after = null
    ) {
        $this->source = $source;
        $this->range = $range;
        $this->column = $column;
        $this->indexName = $indexName;
        $this->chunkSize = $chunkSize;
        $this->maxChunks = $maxChunks;
        $this->after = $after;
    }

    public function handle(Client $elasticsearch): void
    {
        [$lastPartition, $lastKey] = $this->after ?? [null, null];
        $chunks = 0;

        do {
            if ($this->batch() !== null && $this->batch()->cancelled()) {
                return;
            }

            $models = $this->nextChunk($lastPartition, $lastKey);
            if ($models->isEmpty()) {
                return;
            }

            /** @var \Illuminate\Database\Eloquent\Model $last */
            $last = $models->last();
            $lastKey = $last->getKey();
            $lastPartition = $last->getAttribute($this->column);

            $searchable = $models->filter->shouldBeSearchable();
            if ($searchable->isNotEmpty() && ! $this->flush($elasticsearch, $searchable)) {
                return;
            }

            $more = $models->count() === $this->chunkSize;
            $chunks++;
            $batch = $this->batch();
            if ($more && $batch !== null && $this->maxChunks > 0 && $chunks >= $this->maxChunks) {
                // A full last chunk does not prove rows remain: a range
                // planned at exactly this size ends right here. Check before
                // adding a job that would find nothing.
                if (! $this->rowsAfter($lastPartition, $lastKey)->exists()) {
                    return;
                }
                // Added before this job finishes, so the batch never sees
                // zero pending jobs while part of the range is left.
                $batch->add([new self(
                    $this->source,
                    $this->range,
                    $this->column,
                    $this->indexName,
                    $this->chunkSize,
                    $this->maxChunks,
                    [$lastPartition, $lastKey]
                )]);

                return;
            }
        } while ($more);
    }

    /**
     * Fetch the next chunk of the range, ordered by (partition, key)
     * and continuing after the last seen row.
     *
     * @param  mixed  $lastPartition
     * @param  mixed  $lastKey
     * @return EloquentCollection<int, \Illuminate\Database\Eloquent\Model>
     */
    private function nextChunk($lastPartition, $lastKey): EloquentCollection
    {
        return $this->rowsAfter($lastPartition, $lastKey)->limit($this->chunkSize)->get();
    }

    /**
     * The rows of the range that come after the last seen row, in the
     * order the range is imported.
     *
     * @param  mixed  $lastPartition
     * @param  mixed  $lastKey
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    private function rowsAfter($lastPartition, $lastKey): Builder
    {
        $query = $this->source->query()->reorder();
        $model = $query->getModel();
        $keyName = $model->getKeyName();
        $qualifiedColumn = $model->qualifyColumn($this->column);
        $qualifiedKey = $model->getQualifiedKeyName();

        if ($this->range->isNullBucket()) {
            $query->whereNull($qualifiedColumn)->orderBy($qualifiedKey);
            if ($lastKey !== null) {
                $query->where($qualifiedKey, '>', $lastKey);
            }
        } elseif ($this->column === $keyName) {
            $query->orderBy($qualifiedKey);
            if ($lastKey !== null) {
                $query->where($qualifiedKey, '>', $lastKey);
            } elseif ($this->range->from() !== null) {
                $query->where($qualifiedKey, '>=', $this->range->from());
            }
            if ($this->range->to() !== null) {
                $query->where($qualifiedKey, '<', $this->range->to());
            }
        } else {
            // NULL partition values belong to the null bucket. A range with
            // no lower bound would otherwise pick them up too, and NULL
            // breaks the tuple comparison used for paging.
            $query->whereNotNull($qualifiedColumn);
            if ($this->range->from() !== null) {
                $query->where($qualifiedColumn, '>=', $this->range->from());
            }
            if ($this->range->to() !== null) {
                $query->where($qualifiedColumn, '<', $this->range->to());
            }
            $query->orderBy($qualifiedColumn)->orderBy($qualifiedKey);
            if ($lastKey !== null) {
                // Tuple comparison (partition, key) > (last partition, last key)
                // in expanded form: MySQL does not use indexes for row constructors.
                $query->where(function (Builder $tuple) use ($qualifiedColumn, $qualifiedKey, $lastPartition, $lastKey) {
                    $tuple->where($qualifiedColumn, '>', $lastPartition)
                        ->orWhere(function (Builder $tie) use ($qualifiedColumn, $qualifiedKey, $lastPartition, $lastKey) {
                            $tie->where($qualifiedColumn, '=', $lastPartition)
                                ->where($qualifiedKey, '>', $lastKey);
                        });
                });
            }
        }

        return $query;
    }

    /**
     * Returns false when the import alias is gone: the import was
     * revoked, so the job should stop instead of failing. Thanks to
     * require_alias the rejected write cannot auto-create the index
     * it targets.
     *
     * @param  EloquentCollection<int, \Illuminate\Database\Eloquent\Model>  $models
     */
    private function flush(Client $elasticsearch, EloquentCollection $models): bool
    {
        // A snapshot write: it must lose to any live change that
        // already reached this document, however the two writes
        // happened to be ordered on the way in.
        $params = new Bulk($this->indexName, true, Bulk::WRITER_SNAPSHOT);
        $params->index($models->all());
        /** @var Elasticsearch $elasticResponse */
        $elasticResponse = $elasticsearch->bulk($params->toArray());
        $result = new BulkResult($elasticResponse->asArray());

        if ($result->allTargetsMissing()) {
            return false;
        }
        if ($result->hasFatalErrors()) {
            throw new \Exception('Bulk import error: '.$result->toJson());
        }

        return true;
    }
}
