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
     * @var bool
     */
    private $catchUp;

    public function __construct(ImportSource $source, Index $index, bool $catchUp = false)
    {
        $this->source = $source;
        $this->index = $index;
        $this->catchUp = $catchUp;
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

        $since = $this->catchUp ? new \DateTimeImmutable('now') : null;
        $plan = RangePlanner::plan($source, $chunkSize, $chunksPerRange);

        $index = $this->index;
        $connection = $this->source->syncWithSearchUsing();
        $queue = $this->source->syncWithSearchUsingQueue();

        if ($plan->isEmpty()) {
            self::dispatchFinish($this->source, $index, $connection, $queue, $since);

            return;
        }

        $column = $plan->column();
        $jobs = array_map(function (Range $range) use ($source, $column, $index, $chunkSize) {
            return new ImportRange($source, $range, $column, $index->name(), $chunkSize);
        }, $plan->ranges());

        /** @var ImportSource $importSource */
        $importSource = $source;
        $batch = Bus::batch($jobs)
            ->name('scout-import:'.$importSource->searchableAs())
            ->then(function (Batch $batch) use ($importSource, $index, $connection, $queue, $since) {
                self::dispatchFinish($importSource, $index, $connection, $queue, $since);
            });

        if ($connection !== null) {
            $batch->onConnection($connection);
        }
        if ($queue !== null) {
            $batch->onQueue($queue);
        }

        $batch->dispatch();
    }

    private static function dispatchFinish(ImportSource $source, Index $index, ?string $connection, ?string $queue, ?\DateTimeInterface $catchUpSince = null): void
    {
        $finish = FinishImport::dispatch($source, $index, $catchUpSince);
        if ($connection !== null) {
            $finish->onConnection($connection);
        }
        if ($queue !== null) {
            $finish->onQueue($queue);
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
