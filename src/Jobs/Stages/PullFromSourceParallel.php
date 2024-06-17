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
final class PullFromSourceParallel implements StageInterface
{
    /**
     * @var int
     */
    const DEFAULT_HANDLER_COUNT = 1;

    /**
     * @var string
     */
    const DEFAULT_QUEUE_NAME = 'indexing-queue';

    /**
     * @var ImportSource
     */
    private $source;

    /**
     * @var array
     */
    private $handledJobs = [];

    /**
     * @var array
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

        foreach (range(1, config('scout.chunk.handlers', self::DEFAULT_HANDLER_COUNT)) as $i) {
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
    public function handle(Client $elasticsearch = null): void
    {
        if (count($this->dispatchedJobIds) > 0) {
            $jobs = TrackedJob::findMany($this->dispatchedJobIds);
            $failedJobs = $jobs->filter(function ($job) {
                return $job->status === TrackedJob::STATUS_FAILED;
            });
            if ($failedJobs->isNotEmpty()) {
                $jobs->each(function (TrackedJob $job) {
                    $job->markAsFailed();
                });
                throw new \Exception('Failed to process jobs: '.implode(', ', $failedJobs->pluck('id')->toArray()));
            }
            $finishedJobs = $jobs->filter(function ($job) {
                return $job->status === TrackedJob::STATUS_FINISHED;
            });
            $this->handledJobs = array_merge($this->handledJobs, $finishedJobs->pluck('id')->toArray());
            $this->advanceBy += $finishedJobs->count();
            $this->dispatchedJobIds = $jobs->filter(function ($job) {
                return $job->status !== TrackedJob::STATUS_FINISHED;
            })->pluck('id')->toArray();
        }

        if (count($this->handledJobs) + count($this->dispatchedJobIds) > $this->source->getTotalChunks()) {
            return;
        }

        $results = $this->source->get()->filter->shouldBeSearchable();

        if (! $results->isEmpty()) {
            $job = new ProcessSearchable($results);
            dispatch($job)->onQueue($this->getNextQueue())->onConnection($this->source->syncWithSearchUsing());
            $this->dispatchedJobIds[] = $job->trackedJob->getKey();
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
     * @return PullFromSourceParallel|null
     */
    public static function chunked(ImportSource $source): PullFromSourceParallel|null
    {
        $source = $source->chunked();

        if ($source === null) {
            return null;
        }

        return new static($source);
    }
}
