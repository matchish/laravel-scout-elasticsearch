<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\Batch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\ElasticSearch\ImportAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\FinishImport;
use Matchish\ScoutElasticSearch\Jobs\ImportBatches;
use Matchish\ScoutElasticSearch\Jobs\ImportRange;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;
use Matchish\ScoutElasticSearch\Searchable\Partitionable;
use Matchish\ScoutElasticSearch\Searchable\Range;
use Matchish\ScoutElasticSearch\Searchable\RangePlan;
use Matchish\ScoutElasticSearch\Searchable\RangePlanner;

/**
 * Plans the key ranges and dispatches one ImportRange job per range
 * as a named batch. When the batch finishes, FinishImport refreshes
 * the new index and switches the aliases. The stage never waits for
 * the batch, so a worker running the import cannot deadlock itself;
 * WaitForImportRanges does the waiting when it is safe.
 *
 * @internal
 */
final class DispatchImportRanges implements StageInterface
{
    const DEFAULT_CHUNKS_PER_RANGE = 8;

    /**
     * @var ImportSource
     */
    private $source;

    /**
     * @var Index
     */
    private $index;

    /**
     * @var FinishImport
     */
    private $finish;

    /**
     * @var RangePlan|null
     */
    private $plan;

    /**
     * @var string|null
     */
    private $batchId;

    public function __construct(ImportSource $source, Index $index, FinishImport $finish)
    {
        $this->source = $source;
        $this->index = $index;
        $this->finish = $finish;
    }

    /**
     * The plan is computed once and reused. Asking for it early — the
     * progress bar needs the range count before the import starts —
     * also surfaces an unusable partition key before any index is
     * created.
     */
    public function plan(): RangePlan
    {
        if ($this->plan !== null) {
            return $this->plan;
        }

        $source = $this->source;
        if (! $source instanceof Partitionable) {
            throw new \InvalidArgumentException(sprintf(
                'Parallel import requires an import source implementing [%s], but [%s] does not. Run the import without --parallel, or add the interface to your custom import source.',
                Partitionable::class,
                get_class($source)
            ));
        }

        $configChunksPerRange = config('elasticsearch.parallel.chunks_per_range', self::DEFAULT_CHUNKS_PER_RANGE);
        $chunksPerRange = is_numeric($configChunksPerRange) ? (int) $configChunksPerRange : self::DEFAULT_CHUNKS_PER_RANGE;

        return $this->plan = RangePlanner::plan($source, $this->chunkSize(), $chunksPerRange);
    }

    /**
     * The id of the dispatched batch, or null when nothing was
     * dispatched because the source held no rows.
     */
    public function batchId(): ?string
    {
        return $this->batchId;
    }

    public function handle(?Client $elasticsearch = null): void
    {
        $plan = $this->plan();
        /** @var Partitionable $source */
        $source = $this->source;

        $index = $this->index;
        $connection = $this->source->syncWithSearchUsing();
        $queue = $this->source->syncWithSearchUsingQueue();
        $finish = $this->finish;

        if ($plan->isEmpty()) {
            self::dispatchFinish($finish, $connection, $queue);

            return;
        }

        $column = $plan->column();
        $chunkSize = $this->chunkSize();
        $writeTarget = ImportAlias::of($index->name());
        $jobs = array_map(function (Range $range) use ($source, $column, $writeTarget, $chunkSize) {
            return new ImportRange($source, $range, $column, $writeTarget, $chunkSize);
        }, $plan->ranges());

        $batch = Bus::batch($jobs)
            ->name(ImportBatches::name($this->source->searchableAs()))
            ->then(function (Batch $batch) use ($finish, $connection, $queue) {
                // A cancelled batch still reaches this callback, because
                // Laravel counts its skipped jobs as successful.
                if ($batch->cancelled()) {
                    return;
                }
                self::dispatchFinish($finish->forBatch($batch->id), $connection, $queue);
            });

        if ($connection !== null) {
            $batch->onConnection($connection);
        }
        if ($queue !== null) {
            $batch->onQueue($queue);
        }

        try {
            $this->batchId = $batch->dispatch()->id;
        } catch (QueryException $e) {
            throw new \RuntimeException(
                'Parallel import stores its progress in Laravel\'s job batching table, which is missing. Create it with "php artisan queue:batches-table", run "php artisan migrate", and start the import again.',
                0,
                $e
            );
        }
    }

    private function chunkSize(): int
    {
        $configChunkSize = config('scout.chunk.searchable', 500);

        return is_numeric($configChunkSize) ? (int) $configChunkSize : 500;
    }

    private static function dispatchFinish(FinishImport $finish, ?string $connection, ?string $queue): void
    {
        $pending = dispatch($finish);
        if ($connection !== null) {
            $pending->onConnection($connection);
        }
        if ($queue !== null) {
            $pending->onQueue($queue);
        }
    }

    public function title(): string
    {
        return 'Planning ranges';
    }

    public function estimate(): int
    {
        return 1;
    }

    public function advance(): int
    {
        return 1;
    }

    public function completed(): bool
    {
        return true;
    }
}
