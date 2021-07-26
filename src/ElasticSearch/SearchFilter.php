<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;

final class SearchFilter
{

    /**
     * @var BoolQuery
     */
    protected $boolQuery;

    /**
     * @var Builder
     */
    protected $builder;

    /**
     * @var array
     */
    protected $boolQueryTypes = [
        '<' => BoolQuery::MUST,
        '>' => BoolQuery::MUST,
        '>=' => BoolQuery::MUST,
        '<=' => BoolQuery::MUST,
        '=' => BoolQuery::MUST,
        'in' => BoolQuery::MUST,
        'exists' => BoolQuery::MUST,
        'between' => BoolQuery::MUST,
        'startsWith' => BoolQuery::MUST,
        'notExists' => BoolQuery::MUST_NOT,
        '!=' => BoolQuery::MUST_NOT,
        '<>' => BoolQuery::MUST_NOT,
        'notBetween' => BoolQuery::MUST_NOT,
    ];

    /**
     * @param Builder $builder
     */
    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * @param BoolQuery $boolQuery
     * @return BoolQuery
     */
    public function handle(BoolQuery $boolQuery): BoolQuery
    {
        return $this->addWheres($boolQuery);
    }

    /**
     * @return BoolQuery
     */
    private function addWheres(BoolQuery $boolQuery): BoolQuery
    {
        if ($this->hasWheres()) {
            foreach ($this->builder->wheres as $where) {
                $boolQuery->add(
                    app(QueryFilterFactory::class)->create($where),
                    $this->boolQueryTypes[$where->operator]
                );
            }
        }
        return $boolQuery;
    }

    /**
     * @return bool
     */
    private function hasWheres(): bool
    {
        return !empty($this->builder->wheres);
    }
}