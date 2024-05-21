<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Junges\TrackableJobs\Models\TrackedJob;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @internal
 */
final class StopTrackedJobs implements StageInterface
{
    /**
     * @var ImportSource
     */
    private $source;

    /**
     * @param  ImportSource  $source
     */
    public function __construct(ImportSource $source)
    {
        $this->source = $source;
    }

    public function handle(Client $elasticsearch): void
    {
        TrackedJob::query()
            ->where('trackable_type', $this->source->searchableAs())
            ->each(function (TrackedJob $job) {
                $job->markAsFailed('New import started on the same index.');
            });
    }

    public function title(): string
    {
        return 'Stopping queued jobs for this index.';
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
