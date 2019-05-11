<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Jobs\Stages\RefreshIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSource;
use Matchish\ScoutElasticSearch\Jobs\Stages\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\SwitchToNewAndRemoveOldIndex;

class ImportStages extends Collection
{
    /**
     * @param Model $searchable
     * @return Collection
     */
    public static function fromSearchable(Model $searchable)
    {
        $index = Index::fromSearchable($searchable);

        return (new static([
            new CleanUp($searchable),
            new CreateWriteIndex($searchable, $index),
            PullFromSource::chunked($searchable),
            new RefreshIndex($index),
            new SwitchToNewAndRemoveOldIndex($searchable, $index),
        ]))->flatten()->filter();
    }
}
