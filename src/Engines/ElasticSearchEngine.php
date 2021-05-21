<?php

namespace Matchish\ScoutElasticSearch\Engines;

use Elasticsearch\Common\Exceptions\ServerErrorResponseException;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\Builder as BaseBuilder;
use Laravel\Scout\Engines\Engine;
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Refresh;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Search as SearchParams;
use Matchish\ScoutElasticSearch\ElasticSearch\SearchFactory;
use Matchish\ScoutElasticSearch\ElasticSearch\SearchResults;
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
     * @param \Elasticsearch\Client $elasticsearch
     * @return void
     */
    public function __construct(\Elasticsearch\Client $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * {@inheritdoc}
     */
    public function update($models)
    {
        $params = new Bulk();
        $params->index($models);
        $response = $this->elasticsearch->bulk($params->toArray());
        if (array_key_exists('errors', $response) && $response['errors']) {
            $error = new ServerErrorResponseException(json_encode($response, JSON_PRETTY_PRINT));
            throw new \Exception('Bulk update error', $error->getCode(), $error);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete($models)
    {
        $params = new Bulk();
        $params->delete($models);
        $this->elasticsearch->bulk($params->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function flush($model)
    {
        $indexName = $model->searchableAs();
        $exist = $this->elasticsearch->indices()->exists(['index' => $indexName]);
        if ($exist) {
            $body = (new Search())->addQuery(new MatchAllQuery())->toArray();
            $params = new SearchParams($indexName, $body);
            $this->elasticsearch->deleteByQuery($params->toArray());
            $this->elasticsearch->indices()->refresh((new Refresh($indexName))->toArray());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function search(BaseBuilder $builder)
    {
        return $this->performSearch($builder, []);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(BaseBuilder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'from' => ($page - 1) * $perPage,
            'size' => $perPage,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id');
    }

    /**
     * {@inheritdoc}
     */
    public function map(BaseBuilder $builder, $results, $model)
    {
        $hits = app()->makeWith(
            HitsIteratorAggregate::class,
            [
                'results'  => $results,
                'callback' => $builder->queryCallback,
            ]
        );

        return new Collection($hits);
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if (count($results['hits']) === 0) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results['hits']['hits'])->pluck('_id')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds(
            $builder, $objectIds
        )->cursor()->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Create a search index.
     *
     * @param  string  $name
     * @param  array  $options
     * @return mixed
     */
    public function createIndex($name, array $options = [])
    {
        throw new \Error('Not implemented');
    }

    /**
     * Delete a search index.
     *
     * @param  string  $name
     * @return mixed
     */
    public function deleteIndex($name)
    {
        throw new \Error('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total']['value'];
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

        $model = $builder->model;
        $indexName = $builder->index ?: $model->searchableAs();
        $params = new SearchParams($indexName, $searchBody->toArray());

        return $this->elasticsearch->search($params->toArray());
    }
}
