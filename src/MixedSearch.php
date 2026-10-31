<?php

namespace Matchish\ScoutElasticSearch;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;

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
        $builder = new Builder(new class extends Model
        {
            use Searchable;
        }, $query, $callback);

        if (config('scout.soft_delete', false)) {
            // Only soft-deletable models index __soft_deleted, so use "not 1" instead of Scout's "= 0".
            // where() stores it the way Scout does, so withTrashed() and onlyTrashed() still work.
            $builder->where('__soft_deleted', new BoolQuery([BoolQuery::MUST_NOT => new TermQuery('__soft_deleted', 1)]));
        }

        return $builder;
    }
}
