<?php
declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Support\LazyCollection;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Jobs\Stages\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSource;
use Matchish\ScoutElasticSearch\Jobs\Stages\RefreshIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\SwitchToNewAndRemoveOldIndex;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

class ImportStages extends LazyCollection
{
    /**
     * @param ImportSource $source
     * @return LazyCollection
     */
    public static function fromSource(ImportSource $source)
    {
        $index = Index::fromSource($source);

        return self::make(function () use ($source, $index) {
                yield new CleanUp($source);
                yield new CreateWriteIndex($source, $index);
                yield PullFromSource::chunked($source);
                yield new RefreshIndex($index);
                yield new SwitchToNewAndRemoveOldIndex($source, $index);
        });
    }

}
