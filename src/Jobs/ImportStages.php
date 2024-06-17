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
     * @param  bool  $adaptive
     * @return Collection<int, StageInterface>
     */
    public static function fromSource(ImportSource $source, bool $parallel = false, bool $adaptive = false)
    {
        $index = Index::fromSource($source);

        if ($adaptive) {
            $source = $source->chunked();

            if ($source === null) {
                return collect();
            }

            // Performance starts to increase at 75k records for parallel indexing.
            if ($source->getChunkSize() * $source->getTotalChunks() <= 75000) {
                $parallel = false;
            } else {
                $parallel = true;
            }
        }

        if ($parallel && class_exists(\Junges\TrackableJobs\Providers\TrackableJobsServiceProvider::class)) {
            return (new self([
                new StopTrackedJobs($source),
                new CleanUp($source),
                new CreateWriteIndex($source, $index),
                PullFromSourceParallel::chunked($source),
                new CleanUpTrackedJobs($source),
                new RefreshIndex($index),
                new SwitchToNewAndRemoveOldIndex($source, $index),
            ]))->flatten()->filter();
        }

        return (new self([
            new CleanUp($source),
            new CreateWriteIndex($source, $index),
            PullFromSource::chunked($source),
            new RefreshIndex($index),
            new SwitchToNewAndRemoveOldIndex($source, $index),
        ]))->flatten()->filter();
    }
}
