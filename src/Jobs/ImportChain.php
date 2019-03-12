<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 09.03.19
 * Time: 16:47
 */

namespace Matchish\ScoutElasticSearch\Jobs;


use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;

/**
 * @internal
 */
final class ImportChain extends Collection
{
    /**
     * @param string $searchable
     * @return Collection
     */
    public static function from(string $searchable): Collection
    {
        $index = new Index((new $searchable)->searchableAs());
        return new static([
            new CreateWriteIndex($index),
            new MakeAllSearchable($searchable),
            new RefreshIndex($index),
            new SwitchToNewAndRemoveOldIndex($index)
        ]);
    }
}
