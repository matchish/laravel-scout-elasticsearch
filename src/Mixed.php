<?php

namespace Matchish\ScoutElasticSearch;

use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;

final class Mixed
{
    /**
     * Perform a search against the model's indexed data.
     *
     * @param  string  $query
     * @param  \Closure  $callback
     * @return \Laravel\Scout\Builder
     */
    public static function search($query = '', $callback = null)
    {
        return new Builder(new class extends Model {
            use Searchable;
        }, $query, $callback);
    }
}
