<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Matchish\ScoutElasticSearch\DataTransferObjects\WhereFilterData;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\WildcardQuery;

final class QueryFilterFactory
{

    /**
     * @param WhereFilterData $whereFilterData
     * @return BuilderInterface
     */
    public function create(WhereFilterData $whereFilterData): BuilderInterface
    {

        switch ($whereFilterData->operator) {
            case '<>':
            case '!=':
                return new TermQuery($whereFilterData->field, $whereFilterData->value);
            case '>':
                return new RangeQuery($whereFilterData->field, [RangeQuery::GT => $whereFilterData->value]);
            case '>=':
                return new RangeQuery($whereFilterData->field, [RangeQuery::GTE => $whereFilterData->value]);
            case '<':
                return new RangeQuery($whereFilterData->field, [RangeQuery::LT => $whereFilterData->value]);
            case '<=':
                return new RangeQuery($whereFilterData->field, [RangeQuery::LTE => $whereFilterData->value]);
            case 'in':
                return new TermsQuery($whereFilterData->field, $whereFilterData->value);
            case 'startsWith':
                return new WildcardQuery($whereFilterData->field, $whereFilterData->value);
            case 'between':
            case 'notBetween':
                return new RangeQuery($whereFilterData->field, [
                    RangeQuery::GTE => $whereFilterData->value[0],
                    RangeQuery::LTE => $whereFilterData->value[1],
                ]);
            case 'exists':
            case 'notExists':
                return new ExistsQuery($whereFilterData->field);
            case '=':
                return new TermQuery($whereFilterData->field, $whereFilterData->value);
            default:
                return new EmptyQuery();
        }
    }
}