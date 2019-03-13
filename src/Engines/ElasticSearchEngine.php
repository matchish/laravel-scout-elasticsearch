<?php

namespace Matchish\ScoutElasticSearch\Engines;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Matchish\ScoutElasticSearch\ElasticSearch\Payloads\Bulk;

class ElasticSearchEngine extends Engine
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
        $payload = new Bulk();
        $payload->index($models);
        $this->elasticsearch->bulk($payload->toArray());
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection $models
     * @return void
     */
    public function delete($models)
    {

    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {

    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @param  int $perPage
     * @param  int $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {

    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @param  array $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {

    }

    /**
     * Get the filter array for the query.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {

    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @param  mixed $results
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {

    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed $results
     * @return int
     */
    public function getTotalCount($results)
    {
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  Model $model
     * @return void
     */
    public function flush($model)
    {
        /** @var Searchable $model */
        $model = $model;//added to prevent false positive in phpstan
        $indexName = $model->searchableAs();
        $params = [
            'index' => $indexName,
            'body' => ["query" => ['match_all' =>  new \stdClass()]
            ]
        ];
        $this->elasticsearch->deleteByQuery($params);
    }

    /**
     * Determine if the given model uses soft deletes.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
    }
}
