<?php

namespace Matchish\ScoutElasticSearch\Engines;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Searchable;
use Matchish\ScoutElasticSearch\Contracts\SearchableContract;
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Refresh;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Search as SearchParams;
use Matchish\ScoutElasticSearch\ElasticSearch\SearchFactory;
use Matchish\ScoutElasticSearch\ElasticSearch\SearchResults;
use ONGR\ElasticsearchDSL\Query\MatchAllQuery;
use ONGR\ElasticsearchDSL\Search;

/**
 * @phpstan-import-type SearchableModel from SearchableContract
 */
final class ElasticSearchEngine extends Engine
{
    /**
     * The ElasticSearch client.
     *
     * @var Client
     */
    protected $elasticsearch;

    /**
     * Create a new engine instance.
     *
     * @param  Client  $elasticsearch
     * @return void
     */
    public function __construct(Client $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * @param  Collection<int, Model>  $models
     */
    public function update($models)
    {
        $params = new Bulk();
        $params->index($models);
        /** @var Elasticsearch $elasticResponse */
        $elasticResponse = $this->elasticsearch->bulk($params->toArray());
        $response = $elasticResponse->asArray();
        if (array_key_exists('errors', $response) && $response['errors']) {
            /** @var string|bool $json */
            $json = json_encode($response, JSON_PRETTY_PRINT);
            if ($json === false) {
                throw new \Exception('Bulk update error');
            }
            /** @var string $json */
            $error = new ServerResponseException($json);
            throw new \Exception('Bulk update error', $error->getCode(), $error);
        }
    }

    /**
     * @param  Collection<int, Model>  $models
     */
    public function delete($models)
    {
        $params = new Bulk();
        $params->delete($models);
        $this->elasticsearch->bulk($params->toArray());
    }

    /**
     * @param  Model  $model
     */
    public function flush($model)
    {
        $indexName = $model->searchableAs();
        /** @var Elasticsearch $response */
        $response = $this->elasticsearch->indices()->exists(['index' => $indexName]);
        $exist = $response->asBool();
        if ($exist) {
            $body = (new Search())->addQuery(new MatchAllQuery())->toArray();
            $params = new SearchParams($indexName, $body);
            $this->elasticsearch->deleteByQuery($params->toArray());
            $this->elasticsearch->indices()->refresh((new Refresh($indexName))->toArray());
        }
    }

    /**
     * {@inheritdoc}
     * @param Builder<Model> $builder
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, []);
    }

    /**
     * {@inheritdoc}
     * @param Builder<Model> $builder
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'from' => ($page - 1) * $perPage,
            'size' => $perPage,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $results
     * @return \Illuminate\Support\Collection<(int|string), mixed>
     */
    public function mapIds($results)
    {
        if (!is_array($results) || !isset($results['hits'])) {
            return collect();
        }
        $hits = $results['hits'];
        if (!is_array($hits) || !isset($hits['hits'])) {
            return collect();
        }

        // @phpstan-ignore-next-line - Unable to resolve template type
        return collect($hits['hits'])->pluck('_id');
    }

    /**
     * {@inheritdoc}
     * @param Builder<Model> $builder
     * @param mixed $results
     * @param Model $model
     * @return Collection<int, Model>
     */
    public function map(Builder $builder, $results, $model)
    {
        $hits = app()->makeWith(
            HitsIteratorAggregate::class,
            [
                'results' => $results,
                'callback' => $builder->queryCallback,
            ]
        );

        return new Collection($hits);
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  Builder<SearchableModel>  $builder
     * @param  mixed  $results
     * @param  Model  $model
     * @return LazyCollection<int, Model>
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if ((new \ReflectionClass($model))->isAnonymous()) {
            throw new \Error('Not implemented for MixedSearch');
        }

        if (!is_array($results) || !isset($results['hits']['hits']) || count($results['hits']['hits']) === 0) {
            return LazyCollection::make($model->newCollection());
        }

        /** @var array<int, array<string, mixed>> $hits */
        $hits = $results['hits']['hits'];
        /** @var array<int, string> $objectIds */
        $objectIds = collect($hits)->pluck('_id')->values()->all();
        /** @var array<string, int> $objectIdPositions */
        $objectIdPositions = array_flip($objectIds);

        /** @var SearchableModel $model */
        $query = $model->queryScoutModelsByIds($builder, $objectIds);
        /** @var \Illuminate\Database\Eloquent\Builder<SearchableModel> $query */
        $result = $query->cursor()->filter(function ($model) use ($objectIds) {
            /** @var SearchableModel $model */
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            /** @var SearchableModel $model */
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
        
        /** @var LazyCollection<int, Model> */
        return $result;
    }

    /**
     * Create a search index.
     *
     * @param  string  $name
     * @param  array<mixed>  $options
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
     * @param mixed $results
     */
    public function getTotalCount($results)
    {
        if (is_array($results) && isset($results['hits']['total']['value'])) {
            return $results['hits']['total']['value'];
        }
        return 0;
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  array<mixed>  $options
     * @return SearchResults|mixed
     */
    private function performSearch(Builder $builder, $options = [])
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

        /** @var Elasticsearch $response */
        $response = $this->elasticsearch->search($params->toArray());
        return $response->asArray();
    }
}
