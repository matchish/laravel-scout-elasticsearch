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
        return new Builder(new class extends Model
        {
            use Searchable;
        }, $query, $callback);
    }
}
