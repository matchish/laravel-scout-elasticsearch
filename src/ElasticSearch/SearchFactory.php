<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

final class SearchFactory
{
    /**
     * @param Builder $builder
     * @param array $options
     * @return Search
     */
    public static function create(Builder $builder, array $options = []): Search
    {
        $search = new Search();
        $query = new QueryStringQuery($builder->query);
        if (static::hasWhereFilters($builder)) {
            $boolQuery = new BoolQuery();
            $boolQuery = static::addWheres($builder, $boolQuery);
            $boolQuery = static::addWhereIns($builder, $boolQuery);
            $boolQuery->add($query, BoolQuery::MUST);
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
        if (! empty($builder->orders)) {
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
    private static function hasWhereFilters($builder): bool
    {
        return static::hasWheres($builder) || static::hasWhereIns($builder);
    }

    /**
     * @param Builder $builder
     * @param BoolQuery $boolQuery
     * @return BoolQuery
     */
    private static function addWheres($builder, $boolQuery): BoolQuery
    {
        if (static::hasWheres($builder)) {
            foreach ($builder->wheres as $field => $value) {
                $boolQuery->add(new TermQuery((string) $field, $value), BoolQuery::FILTER);
            }
        }

        return $boolQuery;
    }

    /**
     * @param Builder $builder
     * @param BoolQuery $boolQuery
     * @return BoolQuery
     */
    private static function addWhereIns($builder, $boolQuery): BoolQuery
    {
        if (static::hasWhereIns($builder)) {
            foreach ($builder->whereIns as $field => $arrayOfValues) {
                $boolQuery->add(new TermsQuery((string) $field, $arrayOfValues), BoolQuery::FILTER);
            }
        }

        return $boolQuery;
    }

    /**
     * @param Builder $builder
     * @return bool
     */
    private static function hasWheres($builder): bool
    {
        return ! empty($builder->wheres);
    }

    /**
     * @param Builder $builder
     * @return bool
     */
    private static function hasWhereIns($builder): bool
    {
        return isset($builder->whereIns) && ! empty($builder->whereIns);
    }
}
