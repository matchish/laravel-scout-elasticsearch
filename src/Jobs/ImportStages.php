<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUpTrackedJobs;
use Matchish\ScoutElasticSearch\Jobs\Stages\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSource;
use Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSourceParallel;
use Matchish\ScoutElasticSearch\Jobs\Stages\RefreshIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\StageInterface;
use Matchish\ScoutElasticSearch\Jobs\Stages\StopTrackedJobs;
use Matchish\ScoutElasticSearch\Jobs\Stages\SwitchToNewAndRemoveOldIndex;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @extends Collection<int, StageInterface>
 */
class ImportStages extends Collection
{
    /**
     * @param  ImportSource  $source
     * @param  bool  $parallel
     * @return self
     */
    public static function fromSource(ImportSource $source, bool $parallel = false)
    {
        $index = Index::fromSource($source);

        if ($parallel && class_exists(\Junges\TrackableJobs\Providers\TrackableJobsServiceProvider::class)) {
            /** @var array<StageInterface> $stages */
            $stages = [
                new StopTrackedJobs($source),
                new CleanUp($source),
                new CreateWriteIndex($source, $index),
                PullFromSourceParallel::chunked($source),
                new CleanUpTrackedJobs($source),
                new RefreshIndex($index),
                new SwitchToNewAndRemoveOldIndex($source, $index),
            ];
        } else {
            /** @var array<StageInterface> $stages */
            $stages = [
                new CleanUp($source),
                new CreateWriteIndex($source, $index),
                PullFromSource::chunked($source),
                new RefreshIndex($index),
                new SwitchToNewAndRemoveOldIndex($source, $index),
            ];
        }

        return (new self($stages))->flatten()->filter();
    }
}
