<?php

namespace Junges\TrackableJobs\Concerns;

if (!trait_exists(Trackable::class)) {
    trait Trackable
    {
        /** @var \Junges\TrackableJobs\Models\TrackedJob */
        public $trackedJob;

        public function __baseConstruct($model): void {}
    }
}