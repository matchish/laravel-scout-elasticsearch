<?php

namespace App\Library;

use ArrayIterator;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;

/**
 * The example from the "Working with results" section of the README.
 */
final class HighlightedHitsIteratorAggregate implements HitsIteratorAggregate
{
    private array $results;

    /** @var callable|null */
    private $callback;

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

        // Sort the models in the same order as the hits
        // and copy the highlights to each model.
        $result = collect($hits)->map(function (array $hit) use ($models) {
            $model = $models->get(($hit['_source']['__class_name'] ?? '').'::'.$hit['_id']);

            // The document is in the index, but the model is not in the database.
            if ($model === null) {
                return null;
            }

            $model->highlight = $hit['highlight'] ?? [];

            return $model;
        })->filter()->values()->all();

        return new ArrayIterator($result);
    }
}
