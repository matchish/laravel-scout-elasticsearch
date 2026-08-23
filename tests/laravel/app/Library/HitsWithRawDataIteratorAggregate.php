<?php

namespace App\Library;

use ArrayIterator;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;

/**
 * The example from the "Working with results" section of the README.
 */
final class HitsWithRawDataIteratorAggregate implements HitsIteratorAggregate
{
    /** @var array<mixed> */
    private array $results;

    /** @var callable|null */
    private $callback;

    /**
     * @param  array<mixed>  $results
     */
    public function __construct(array $results, ?callable $callback = null)
    {
        $this->results = $results;
        $this->callback = $callback;
    }

    public function getIterator(): ArrayIterator
    {
        $hits = $this->results['hits']['hits'] ?? [];

        // MixedSearch can return hits of different classes.
        // Group them by class and load each group with one query.
        $models = collect($hits)
            ->groupBy('_source.__class_name')
            ->flatMap(function (Collection $classHits, string $class) {
                $model = new $class;
                $model->setKeyType('string');

                $builder = new Builder($model, '');

                // Keep the ->query() callback so eager loading still works.
                if ($this->callback) {
                    $builder->query($this->callback);
                }

                return $model->getScoutModelsByIds($builder, $classHits->pluck('_id')->all())
                    ->keyBy(function ($model) use ($class) {
                        return $class.'::'.$model->getScoutKey();
                    });
            });

        // Sort the models in the same order as the hits.
        $result = collect($hits)->map(function (array $hit) use ($models) {
            $model = $models->get(($hit['_source']['__class_name'] ?? '').'::'.$hit['_id']);

            // The document is in the index, but the model is not in the database.
            if ($model === null) {
                return null;
            }

            // Your class replaces EloquentHitsIteratorAggregate,
            // so fill the ElasticParams trait here as well.
            $model->setElasticsearchScore((float) ($hit['_score'] ?? 0));
            $model->setElasticsearchHighlight($hit['highlight'] ?? []);

            $model->hit = $hit;

            return $model;
        })->filter()->values()->all();

        return new ArrayIterator($result);
    }
}
