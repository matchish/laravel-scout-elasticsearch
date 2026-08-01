<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\FinishImport;
use Matchish\ScoutElasticSearch\Jobs\ImportRange;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;
use Matchish\ScoutElasticSearch\Searchable\Partitionable;
use Matchish\ScoutElasticSearch\Searchable\Range;
use Matchish\ScoutElasticSearch\Searchable\RangePlanner;

/**
 * Plans the key ranges and dispatches one ImportRange job per range
 * as a named batch. When the batch finishes, FinishImport refreshes
 * the new index and switches the aliases. The stage never waits for
 * the batch, so a worker running the import cannot deadlock itself.
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

    public function __construct(ImportSource $source, Index $index, FinishImport $finish)
    {
        $this->source = $source;
        $this->index = $index;
        $this->finish = $finish;
    }

    public function handle(Client $elasticsearch): void
    {
        $source = $this->source;
        if (! $source instanceof Partitionable) {
            throw new \InvalidArgumentException(sprintf(
                'Parallel import requires an import source implementing [%s], but [%s] does not. Run the import without --parallel, or add the interface to your custom import source.',
                Partitionable::class,
                get_class($source)
            ));
        }

        $configChunkSize = config('scout.chunk.searchable', 500);
        $chunkSize = is_numeric($configChunkSize) ? (int) $configChunkSize : 500;
        $configChunksPerRange = config('elasticsearch.parallel.chunks_per_range', self::DEFAULT_CHUNKS_PER_RANGE);
        $chunksPerRange = is_numeric($configChunksPerRange) ? (int) $configChunksPerRange : self::DEFAULT_CHUNKS_PER_RANGE;

        $plan = RangePlanner::plan($source, $chunkSize, $chunksPerRange);

        $index = $this->index;
        $connection = $this->source->syncWithSearchUsing();
        $queue = $this->source->syncWithSearchUsingQueue();
        $finish = $this->finish;

        if ($plan->isEmpty()) {
            self::dispatchFinish($finish, $connection, $queue);

            return;
        }

        $column = $plan->column();
        $jobs = array_map(function (Range $range) use ($source, $column, $index, $chunkSize) {
            return new ImportRange($source, $range, $column, $index->name(), $chunkSize);
        }, $plan->ranges());

        $batch = Bus::batch($jobs)
            ->name('scout-import:'.$this->source->searchableAs())
            ->then(function (Batch $batch) use ($finish, $connection, $queue) {
                self::dispatchFinish($finish, $connection, $queue);
            });

        if ($connection !== null) {
            $batch->onConnection($connection);
        }
        if ($queue !== null) {
            $batch->onQueue($queue);
        }

        $batch->dispatch();
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
        return 'Indexing...';
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
