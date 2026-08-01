<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\FinishImport;
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
        $jobs = array_map(function (Range $range) use ($source, $column, $index, $chunkSize) {
            return new ImportRange($source, $range, $column, $index->name(), $chunkSize);
        }, $plan->ranges());

        $batch = Bus::batch($jobs)
            ->name('scout-import:'.$this->source->searchableAs())
            ->then(function () use ($finish, $connection, $queue) {
                self::dispatchFinish($finish, $connection, $queue);
            });

        if ($connection !== null) {
            $batch->onConnection($connection);
        }
        if ($queue !== null) {
            $batch->onQueue($queue);
        }

        $this->batchId = $batch->dispatch()->id;
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
