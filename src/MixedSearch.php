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
    public static function search(string $query = '', $callback = null): Builder
    {
        $model = new class extends Model
        {
            use Searchable;
        };

        $builder = new class($model, $query, $callback) extends Builder
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

        if (config('scout.soft_delete', false)) {
            // Only soft-deletable models index __soft_deleted, so use "not 1" instead of Scout's "= 0".
            // where() stores it the way Scout does, so withTrashed() and onlyTrashed() still work.
            $builder->where('__soft_deleted', new BoolQuery([BoolQuery::MUST_NOT => new TermQuery('__soft_deleted', 1)]));
        }

        return $builder;
    }
}
