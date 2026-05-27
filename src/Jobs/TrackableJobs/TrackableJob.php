<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\TrackableJobs;

/**
 * @phpstan-import-type TrackedJobModelType from TrackedJobContract
 */
abstract class TrackableJob
{
    /**
     * @var TrackedJobModelType|null $trackedJob
     */
    public ?TrackedJobContract $trackedJob = null;

    public function __construct()
    {
        /** @var class-string<TrackedJobModelType> $modelClass */
        $modelClass = config('elasticsearch.tracked_jobs.model', TrackedJob::class);

        /** @var TrackedJobModelType $model */
        $model = $modelClass::create([
            'name' => static::class,
            'status' => TrackedJobContract::STATUS_QUEUED,
            'trackable_type' => $this->trackableType(),
            'trackable_id' => $this->trackableKey(),
        ]);

        $this->trackedJob = $model;
    }

    abstract public function trackableKey(): ?string;

    abstract public function trackableType(): ?string;

    /**
     * @return array<int, TrackableJobMiddleware>
     */
    public function middleware(): array
    {
        return [new TrackableJobMiddleware()];
    }
}
