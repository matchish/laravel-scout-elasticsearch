<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanLastId;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Jobs\Stages\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSource;
use Matchish\ScoutElasticSearch\Jobs\Stages\RefreshIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\SwitchToNewAndRemoveOldIndex;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

class ImportStages extends Collection
{
    /**
     * @param ImportSource $source
     * @return Collection
     */
    public static function fromSource(ImportSource $source)
    {
        $index = Index::fromSource($source);

        return (new self([
            new CleanUp($source),
            new CreateWriteIndex($source, $index),
            new CleanLastId(), // Cleans the last ID of the cache left over from the last time
            PullFromSource::chunked($source),
            new RefreshIndex($index),
            new SwitchToNewAndRemoveOldIndex($source, $index),
        ]))->flatten()->filter();
    }
}
