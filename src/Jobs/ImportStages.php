<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Support\Collection;
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
        $index = $source->defineIndex();

        return (new self([
            new CleanUp($source),
            new CreateWriteIndex($source, $index),
            PullFromSource::chunked($source),
            new RefreshIndex($index),
            new SwitchToNewAndRemoveOldIndex($source, $index),
        ]))->flatten()->filter();
    }
}
