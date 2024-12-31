<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Illuminate\Support\Arr;
use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

final class SearchFactory
{
    /**
     * @param  Builder  $builder
     * @param  array  $enforceOptions
     * @return Search
     */
    public static function create(Builder $builder, array $enforceOptions = []): Search
    {
        $options = static::prepareOptions($builder, $enforceOptions);
        $search = new Search();
        if (static::hasWhereFilters($builder)) {
            $boolQuery = new BoolQuery();
            $boolQuery = static::addWheres($builder, $boolQuery);
            $boolQuery = static::addWhereIns($builder, $boolQuery);
            $boolQuery = static::addWhereNotIns($builder, $boolQuery);
            if (! empty($builder->query)) {
                $boolQuery->add(new QueryStringQuery($builder->query));
            }
            $search->addQuery($boolQuery);
        } elseif (! empty($builder->query)) {
            $search->addQuery(new QueryStringQuery($builder->query));
        }
        if (array_key_exists('from', $options)) {
            $search->setFrom($options['from']);
        }
        if (array_key_exists('size', $options)) {
            $search->setSize($options['size']);
        }
        if (array_key_exists('source', $options)) {
            $search->setSource($options['source']);
        }
        if (! empty($builder->orders)) {
            foreach ($builder->orders as $order) {
                $search->addSort(new FieldSort($order['column'], $order['direction']));
            }
        }

        return $search;
    }

    /**
     * @param  Builder  $builder
     * @return bool
     */
    private static function hasWhereFilters($builder): bool
    {
        return static::hasWheres($builder) || static::hasWhereIns($builder) || static::hasWhereNotIns($builder);
    }

    /**
     * @param  Builder  $builder
     * @param  BoolQuery  $boolQuery
     * @return BoolQuery
     */
    private static function addWheres($builder, $boolQuery): BoolQuery
    {
        if (static::hasWheres($builder)) {
            foreach ($builder->wheres as $field => $value) {
                if (! ($value instanceof BuilderInterface)) {
                    $value = new TermQuery((string) $field, $value);
                }
                $boolQuery->add($value, BoolQuery::FILTER);
            }
        }

        return $boolQuery;
    }

    /**
     * @param  Builder  $builder
     * @param  BoolQuery  $boolQuery
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
     * @param  Builder  $builder
     * @param  BoolQuery  $boolQuery
     * @return BoolQuery
     */
    private static function addWhereNotIns($builder, $boolQuery): BoolQuery
    {
        if (static::hasWhereNotIns($builder)) {
            foreach ($builder->whereNotIns as $field => $arrayOfValues) {
                $boolQuery->add(new TermsQuery((string) $field, $arrayOfValues), BoolQuery::MUST_NOT);
            }
        }

        return $boolQuery;
    }

    /**
     * @param  Builder  $builder
     * @return bool
     */
    private static function hasWheres($builder): bool
    {
        return ! empty($builder->wheres);
    }

    /**
     * @param  Builder  $builder
     * @return bool
     */
    private static function hasWhereIns($builder): bool
    {
        return isset($builder->whereIns) && ! empty($builder->whereIns);
    }

    /**
     * @param  Builder  $builder
     * @return bool
     */
    private static function hasWhereNotIns($builder): bool
    {
        return isset($builder->whereNotIns) && ! empty($builder->whereNotIns);
    }

    private static function prepareOptions(Builder $builder, array $enforceOptions = []): array
    {
        $options = [];

        if (isset($builder->limit)) {
            $options['size'] = $builder->limit;
        }

        return array_merge($options, self::supportedOptions($builder), $enforceOptions);
    }

    private static function supportedOptions(Builder $builder): array
    {
        return Arr::only($builder->options, [
            'from',
            'source',
        ]);
    }
}
