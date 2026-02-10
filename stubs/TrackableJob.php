<?php

namespace Junges\TrackableJobs;

if (!class_exists(\Junges\TrackableJobs\TrackableJob::class)) {
    abstract class TrackableJob {
        public $trackedJob;

        public function __construct() {}
    }
}