<?php

namespace Junges\TrackableJobs\Enums;

if (! class_exists(TrackedJobStatus::class)) {
    class TrackedJobStatus
    {
        public const Failed = 'failed';
        public const Finished = 'finished';
    }
}
