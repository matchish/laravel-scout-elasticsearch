<?php

namespace Matchish\ScoutElasticSearch;

use Laravel\Scout\Builder;

final class MixedSearch
{
    /**
     * Perform a search against the model's indexed data.
     *
     * @param  string  $query
     * @param  \Closure  $callback
     * @return \Laravel\Scout\Builder
     */
    public static function search(string $query = '', $callback = null): Builder
    {
        return new Builder(new MixedModel(), $query, $callback);
    }
}
