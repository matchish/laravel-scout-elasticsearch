<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

final class BuildSearchBodyAction
{
    /**
     * @param Builder $builder
     * @param array $options
     * @return Search
     */
    public function handle(Builder $builder, array $options = []): Search
    {
        $search = new Search();
        $query = new QueryStringQuery($builder->query);
        if ($this->hasWheres($builder)) {
            $boolQuery = (new SearchFilter($builder))->handle(new BoolQuery());
            $search->addQuery($boolQuery);
        } else {
            $search->addQuery($query);
        }
        if (array_key_exists('from', $options)) {
            $search->setFrom($options['from']);
        }
        if (array_key_exists('size', $options)) {
            $search->setSize($options['size']);
        }
        if (!empty($builder->orders)) {
            foreach ($builder->orders as $order) {
                $search->addSort(new FieldSort($order['column'], $order['direction']));
            }
        }

        return $search;
    }

    /**
     * @param Builder $builder
     * @return bool
     */
    private function hasWheres($builder): bool
    {
        return !empty($builder->wheres);
    }
}