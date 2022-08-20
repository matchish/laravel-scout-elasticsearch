<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Elastic\Elasticsearch\Response\Elasticsearch;
use IteratorAggregate;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;
use Traversable;

/**
 * @internal
 */
final class EloquentHitsIteratorAggregate implements IteratorAggregate
{
    /**
     * @var array
     */
    private $results;
    /**
     * @var callable|null
     */
    private $callback;

    /**
     * @param  array|Elasticsearch  $results
     * @param  callable|null  $callback
     */
    public function __construct(array|Elasticsearch $results, callable $callback = null)
    {
        $this->results = $results;
        $this->callback = $callback;
    }

    /**
     * Retrieve an external iterator.
     *
     * @link https://php.net/manual/en/iteratoraggregate.getiterator.php
     *
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     *                     <b>Traversable</b>
     *
     * @since 5.0.0
     */
    public function getIterator()
    {
        $hits = collect();
        if ($this->results['hits']['total']) {
            $hits = $this->results['hits']['hits'];
            $models = collect($hits)->groupBy('_source.__class_name')
                ->map(function ($results, $class) {
                    /** @var Searchable $model */
                    $model = new $class;
                    $model->setKeyType('string');
                    $builder = new Builder($model, '');
                    if (! empty($this->callback)) {
                        $builder->query($this->callback);
                    }

                    return $models = $model->getScoutModelsByIds(
                        $builder, $results->pluck('_id')->all()
                    );
                })
                ->flatten()->keyBy(function ($model) {
                    return get_class($model).'::'.$model->getScoutKey();
                });
            $hits = collect($hits)->map(function ($hit) use ($models) {
                $key = $hit['_source']['__class_name'].'::'.$hit['_id'];

                return isset($models[$key]) ? $models[$key] : null;
            })->filter()->all();
        }

        return new \ArrayIterator((array) $hits);
    }
}
