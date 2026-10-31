<?php

namespace Matchish\ScoutElasticSearch;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;

final class MixedSearch
{
    /**
     * Perform a search against the model's indexed data.
     *
     * @param  string  $query
     * @param  \Closure  $callback
     * @return \Laravel\Scout\Builder
     */
    // @phpstan-ignore-next-line - This method is not actually called, so the class string is not validated
    public static function search(string $query = '', $callback = null): Builder
    {
        $model = new class extends Model
        {
            use Searchable;
        };

        return new class($model, $query, $callback) extends Builder
        {
            protected function getTotalCount($results)
            {
                /** @var \Laravel\Scout\Engines\Engine */
                $engine = $this->engine();

                // There is no table to count rows in, so use the Elasticsearch total.
                // Scout's docs say query() must not filter rows; filters belong in where().
                return $engine->getTotalCount($results);
            }
        };
    }
}
