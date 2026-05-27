<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\TrackableJobs;

class TrackableJobMiddleware
{
    /**
     * @param  mixed  $job
     * @param  callable  $next
     */
    public function handle($job, callable $next): void
    {
        if (! $job instanceof TrackableJob) {
            $next($job);

            return;
        }

        /** @var TrackedJobContract|null $trackedJob */
        $trackedJob = $job->trackedJob ?? null;

        $trackedJob?->markAsRunning();

        try {
            $next($job);
            $trackedJob?->markAsFinished();
        } catch (\Throwable $e) {
            $trackedJob?->markAsFailed($e->getMessage());
            throw $e;
        }
    }
}
