<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

final class SearchFactory
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  array<mixed>  $enforceOptions
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
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return bool
     */
    private static function hasWhereFilters($builder): bool
    {
        return static::hasWheres($builder) || static::hasWhereIns($builder) || static::hasWhereNotIns($builder);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  BoolQuery  $boolQuery
     * @return BoolQuery
     */
    private static function addWheres($builder, $boolQuery): BoolQuery
    {
        if (static::hasWheres($builder)) {
            foreach ($builder->wheres as $field => $where) {
                if (is_array($where) && isset($where['field'])) {
                    // Post v11.1.0 scout
                    $field = $where['field'];
                    $operator = $where['operator'] === '<>' ? '!=' : $where['operator'];
                    $value = $where['value'];
                } else {
                    $operator = '=';
                    $value = $where;
                }

                if ($value === null && in_array($operator, ['=', '!='], true)) {
                    // Elasticsearch does not index null, so check if the field has a value
                    $boolQuery->add(new ExistsQuery((string) $field), $operator === '=' ? BoolQuery::MUST_NOT : BoolQuery::FILTER);
                    continue;
                }

                $query = $value instanceof BuilderInterface ? $value : match ($operator) {
                    '=', '!=' => new TermQuery((string) $field, $value),
                    '>' => new RangeQuery((string) $field, [RangeQuery::GT => $value]),
                    '>=' => new RangeQuery((string) $field, [RangeQuery::GTE => $value]),
                    '<' => new RangeQuery((string) $field, [RangeQuery::LT => $value]),
                    '<=' => new RangeQuery((string) $field, [RangeQuery::LTE => $value]),
                    default => throw new InvalidArgumentException(sprintf(
                        'Operator [%s] is not supported by the Elasticsearch engine. Supported operators: =, !=, <>, >, >=, <, <=.',
                        is_string($operator) ? $operator : get_debug_type($operator)
                    )),
                };

                $boolQuery->add($query, $operator === '!=' ? BoolQuery::MUST_NOT : BoolQuery::FILTER);
            }
        }

        return $boolQuery;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
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
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
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
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return bool
     */
    private static function hasWheres($builder): bool
    {
        return ! empty($builder->wheres);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return bool
     */
    private static function hasWhereIns($builder): bool
    {
        return ! empty($builder->whereIns);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return bool
     */
    private static function hasWhereNotIns($builder): bool
    {
        return property_exists($builder, 'whereNotIns') && ! empty($builder->whereNotIns);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  array<mixed>  $enforceOptions
     * @return array<mixed>
     */
    private static function prepareOptions(Builder $builder, array $enforceOptions = []): array
    {
        $options = [];

        if (isset($builder->limit)) {
            $options['size'] = $builder->limit;
        }

        return array_merge($options, self::supportedOptions($builder), $enforceOptions);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return array<mixed>
     */
    private static function supportedOptions(Builder $builder): array
    {
        return Arr::only($builder->options, [
            'from',
            'source',
        ]);
    }
}
