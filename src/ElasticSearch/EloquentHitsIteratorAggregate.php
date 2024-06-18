<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Illuminate\Database\Eloquent\Model;
use IteratorAggregate;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;
use Matchish\ScoutElasticSearch\Traits\ElasticParams;
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
     * @param  array  $results
     * @param  callable|null  $callback
     */
    public function __construct(array $results, callable $callback = null)
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
    public function getIterator(): Traversable
    {
        $hits = collect();
        if ($this->results['hits']['total']) {
            /** @var array<int, array<string, mixed>> */
            $hits = $this->results['hits']['hits'];

            /** @var array<int, string> $hasTrait */
            $hasTrait = [];
            $models = collect($hits)->groupBy('_source.__class_name')
                ->map(function ($results, $class) use (&$hasTrait) {
                    /** @var Model&Searchable $model */
                    $model = new $class;
                    $model->setKeyType('string');
                    $builder = new Builder($model, '');
                    if (! empty($this->callback)) {
                        $builder->query($this->callback);
                    }

                    if (method_exists($model, 'setElasticsearchScore') && method_exists($model, 'setElasticsearchHighlight')) {
                        $hasTrait[] = get_class($model);
                    }

                    return $models = $model->getScoutModelsByIds(
                        $builder, $results->pluck('_id')->all()
                    );
                })
                ->flatten()->keyBy(function (Model|Searchable $model) {
                    return get_class($model).'::'.$model->getScoutKey();
                });
            $hits = collect($hits)->map(function ($hit) use ($models, $hasTrait) {
                /** @var array<mixed, mixed> $hit */
                if (! isset($hit['_source'], $hit['_id'])) {
                    return null;
                }
                $source = $hit['_source'];
                if (! isset($source['__class_name'])) {
                    return null;
                }

                $key = $source['__class_name'].'::'.$hit['_id'];

                if (! isset($models[$key])) {
                    return null;
                }

                /** @var Model&Searchable&ElasticParams $model */
                $model = $models[$key];

                if (in_array($source['__class_name'], $hasTrait)) {
                    if (isset($hit['_score']) && ! empty($hit['_score'])) {
                        $model->setElasticsearchScore((float) $hit['_score']);
                    }

                    if (isset($hit['highlight']) && ! empty($hit['highlight'])) {
                        $model->setElasticsearchHighlight($hit['highlight']);
                    }
                }

                return $model;
            })->filter()->all();
        }

        return new \ArrayIterator((array) $hits);
    }
}
