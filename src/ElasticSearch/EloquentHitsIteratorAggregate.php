<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Illuminate\Database\Eloquent\Model;
use IteratorAggregate;
use Laravel\Scout\Builder;
use Matchish\ScoutElasticSearch\Contracts\SearchableContract;
use SearchableModelWithElasticParams;
use Traversable;

/**
 * @internal
 * @implements IteratorAggregate<int, Model>
 * 
 * @phpstan-import-type SearchableModel from SearchableContract
 * @phpstan-import-type SearchableModelWithElasticParams from SearchableContract
 */
final class EloquentHitsIteratorAggregate implements IteratorAggregate
{
    /**
     * @var array<mixed>
     */
    private $results;
    /**
     * @var callable|null
     */
    private $callback;

    /**
     * @param  array<mixed>  $results
     * @param  callable|null  $callback
     */
    public function __construct(array $results, ?callable $callback = null)
    {
        $this->results = $results;
        $this->callback = $callback;
    }

    /**
     * Retrieve an external iterator.
     *
     * @link https://php.net/manual/en/iteratoraggregate.getiterator.php
     *
     * @return Traversable<int, Model> An instance of an object implementing <b>Iterator</b> or
     *                     <b>Traversable</b>
     *
     * @since 5.0.0
     */
    public function getIterator(): Traversable
    {
        $hits = collect();
        if (!\is_array($this->results) || !isset($this->results['hits']['total']) || !$this->results['hits']['total']) {
            return new \ArrayIterator([]);
        }
        
        if (!isset($this->results['hits']['hits'])) {
            return new \ArrayIterator([]);
        }
        
        /** @var array<int, array<string, mixed>> */
        $hits = $this->results['hits']['hits'];

        /** @var array<int, string> $hasTrait */
        $hasTrait = [];

        /** @var \Illuminate\Support\Collection<int, Model|SearchableModelWithElasticParams> $models */
        $models = collect($hits)->groupBy('_source.__class_name')
            ->map(function ($results, $class) use (&$hasTrait) {
                /** @var SearchableModel $model */
                $model = new $class;
                $model->setKeyType('string');
                $builder = new Builder($model, '');
                if (! empty($this->callback)) {
                    $builder->query($this->callback);
                }

                if (method_exists($model, 'setElasticsearchScore') && method_exists($model, 'setElasticsearchHighlight')) {
                    $hasTrait[] = \get_class($model);
                }

                /** @var array<int|string> $ids */
                $ids = $results->pluck('_id')->all();
                return $models = $model->getScoutModelsByIds(
                    $builder, $ids
                );
            })
            ->flatten()->keyBy(function (Model $model) {
                /** @var SearchableModel $model */
                return \get_class($model).'::'.$model->getScoutKey();
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

            /** @var SearchableModelWithElasticParams $model */
            $model = $models[$key];

            if (\in_array($source['__class_name'], $hasTrait)) {
                if (isset($hit['_score']) && ! empty($hit['_score'])) {
                    $model->setElasticsearchScore((float) $hit['_score']);
                }

                if (isset($hit['highlight']) && ! empty($hit['highlight'])) {
                    $model->setElasticsearchHighlight($hit['highlight']);
                }
            }

            return $model;
        })->filter()->all();

        /** @var array<int, Model> $hits */
        return new \ArrayIterator($hits);
    }
}
