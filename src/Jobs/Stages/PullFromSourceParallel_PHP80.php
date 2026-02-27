<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Junges\TrackableJobs\Models\TrackedJob;
use Matchish\ScoutElasticSearch\Database\Scopes\FromScope;
use Matchish\ScoutElasticSearch\Database\Scopes\PageScope;
use Matchish\ScoutElasticSearch\Jobs\ProcessSearchable;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @internal
 */
final class PullFromSourceParallel_PHP80 implements StageInterface
{
    /**
     * @var int
     */
    const DEFAULT_HANDLER_COUNT = 1;

    /**
     * @var string
     */
    const DEFAULT_QUEUE_NAME = 'elasticsearch-parallel';

    /**
     * @var ImportSource
     */
    private $source;

    /**
     * @var array<int>
     */
    private $handledJobs = [];

    /**
     * @var array<int>
     */
    private $dispatchedJobIds = [];

    /**
     * @var int
     */
    private $advanceBy = 0;

    /**
     * @var array<string>
     */
    private $queues = [];

    /**
     * @param  ImportSource  $source
     */
    public function __construct(ImportSource $source)
    {
        $this->source = $source;
        $this->queues = [];

        $handlerCount = config('scout.chunk.handlers', self::DEFAULT_HANDLER_COUNT);
        $handlerCountInt = is_int($handlerCount) ? $handlerCount : self::DEFAULT_HANDLER_COUNT;
        foreach (range(1, $handlerCountInt) as $i) {
            $this->queues[] = config('elasticsearch.queue.name', self::DEFAULT_QUEUE_NAME).'-'.$i;
        }
    }

    /**
     * @return string
     */
    private function getNextQueue(): string
    {
        /** @var string $queue */
        $queue = array_shift($this->queues);
        $this->queues[] = $queue;

        return $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(?Client $elasticsearch = null): void
    {
        if (count($this->dispatchedJobIds) > 0) {
            $jobs = TrackedJob::findMany($this->dispatchedJobIds);
            $failedJobs = $jobs->filter(function ($job) {
                // @phpstan-ignore-next-line
                return $job->status === TrackedJob::STATUS_FAILED;
            });
            if ($failedJobs->isNotEmpty()) {
                $jobs->each(function (TrackedJob $job) {
                    $job->markAsFailed();
                });
                /** @var array<int> */
                $failedIds = $failedJobs->pluck('id')->toArray();
                throw new \Exception('Failed to process jobs: '.implode(', ', $failedIds));
            }
            $finishedJobs = $jobs->filter(function ($job) {
                // @phpstan-ignore-next-line
                return $job->status === TrackedJob::STATUS_FINISHED;
            });
            /** @var array<int> */
            $finishedIds = $finishedJobs->pluck('id')->toArray();
            $this->handledJobs = array_merge($this->handledJobs, $finishedIds);
            $this->advanceBy += $finishedJobs->count();
            /** @var array<int> */
            $pendingIds = $jobs->filter(function ($job) {
                // @phpstan-ignore-next-line
                return $job->status !== TrackedJob::STATUS_FINISHED;
            })->pluck('id')->toArray();
            $this->dispatchedJobIds = $pendingIds;
        }

        if (count($this->handledJobs) + count($this->dispatchedJobIds) > $this->source->getTotalChunks()) {
            return;
        }

        $results = $this->source->get()->filter->shouldBeSearchable();

        if (! $results->isEmpty()) {
            $job = new ProcessSearchable($results);
            dispatch($job)->onQueue($this->getNextQueue())->onConnection($this->source->syncWithSearchUsing());
            $trackedJob = $job->trackedJob;
            if ($trackedJob !== null) {
                $key = $trackedJob->getKey();
                if (is_int($key)) {
                    $this->dispatchedJobIds[] = $key;
                }
            }
            if ($results->first()->getKeyType() !== 'int') {
                $this->source->setChunkScope(
                    new PageScope(
                        count($this->handledJobs) + count($this->dispatchedJobIds),
                        $this->source->getChunkSize())
                );
            } else {
                $this->source->setChunkScope(new FromScope($results->last()->getKey(), $this->source->getChunkSize()));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function estimate(): int
    {
        return $this->source->getTotalChunks();
    }

    /**
     * {@inheritdoc}
     */
    public function advance(): int
    {
        $advance = $this->advanceBy;
        $this->advanceBy = 0;

        return $advance;
    }

    /**
     * {@inheritdoc}
     */
    public function title(): string
    {
        return 'Indexing...';
    }

    /**
     * {@inheritdoc}
     */
    public function completed(): bool
    {
        return count($this->handledJobs) >= $this->source->getTotalChunks();
    }

    /**
     * @param  ImportSource  $source
     * @return PullFromSourceParallel_PHP80|null
     */
    public static function chunked(ImportSource $source): ?PullFromSourceParallel_PHP80
    {
        $source = $source->chunked();

        if ($source === null) {
            return null;
        }

        return new static($source);
    }
}
