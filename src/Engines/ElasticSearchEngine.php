<?php

namespace Matchish\ScoutElasticSearch\Engines;

use Laravel\Scout\Builder as BaseBuilder;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Searchable;
use Matchish\ScoutElasticSearch\ElasticSearch\DefaultSearchResults;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Search as SearchParams;
use Matchish\ScoutElasticSearch\ElasticSearch\SearchFactory;
use Matchish\ScoutElasticSearch\ElasticSearch\SearchResults;
use Matchish\ScoutElasticSearch\Pipelines\ImportPipeline;
use ONGR\ElasticsearchDSL\Query\MatchAllQuery;
use ONGR\ElasticsearchDSL\Search;

final class ElasticSearchEngine extends Engine
{
    /**
     * The ElasticSearch client.
     *
     * @var \Elasticsearch\Client
     */
    protected $elasticsearch;

    /**
     * Create a new engine instance.
     *
     * @param  \Elasticsearch\Client $elasticsearch
     * @return void
     */
    public function __construct(\Elasticsearch\Client $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * @inheritdoc
     */
    public function update($models)
    {
        $params = new Bulk();
        $params->index($models);
        $this->elasticsearch->bulk($params->toArray());
    }

    /**
     * @inheritdoc
     */
    public function delete($models)
    {
        $params = new Bulk();
        $params->delete($models);
        $this->elasticsearch->bulk($params->toArray());
    }


    /**
     * @inheritdoc
     */
    public function flush($model)
    {
        $indexName = $model->searchableAs();
        $body = (new Search())->addQuery(new MatchAllQuery())->toArray();
        $params = new SearchParams($indexName, $body);
        $this->elasticsearch->deleteByQuery($params->toArray());
    }

    /**
     * @inheritdoc
     */
    public function search(BaseBuilder $builder)
    {
        return $this->performSearch($builder, []);
    }

    /**
     * @inheritdoc
     */
    public function paginate(BaseBuilder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'from' => ($page - 1) * $perPage,
            'size' => $perPage
        ]);
    }

    /**
     * @inheritdoc
     */
    public function mapIds($results)
    {
        return $results->pluck('_id');
    }

    /**
     * @inheritdoc
     */
    public function map(BaseBuilder $builder, $results, $model)
    {
        return $results->mapTo($model, $builder);
    }

    /**
     * @inheritdoc
     */
    public function getTotalCount($results)
    {
        return $results->total();
    }


    /**
     * @internal
     */
    public function sync($model)
    {
        $pipeline = new ImportPipeline($this->elasticsearch);
        $pipeline->process([Index::fromSearchable($model), $model]);
    }

    /**
     * @param BaseBuilder $builder
     * @param array $options
     * @return SearchResults|mixed
     */
    private function performSearch(BaseBuilder $builder, $options = [])
    {
        $searchBody = SearchFactory::create($builder, $options);
        if ($builder->callback) {
            /** @var callable */
            $callback = $builder->callback;
            return call_user_func(
                $callback,
                $this->elasticsearch,
                $searchBody
            );
        }
        /** @var Searchable $model */
        $model = $builder->model;
        $indexName = $builder->index ?: $model->searchableAs();
        $params = new SearchParams($indexName, $searchBody->toArray());
        return new DefaultSearchResults($this->elasticsearch->search($params->toArray()));
    }

}
