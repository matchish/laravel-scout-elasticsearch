<?php


namespace Matchish\ScoutElasticSearch\ElasticSearch;


use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;

class HighlightedHitsIterator implements \IteratorAggregate
{
    /**
     * @var array
     */
    private $results;

    /**
     * @var callable|null
     */
    private $callback;

    public function __invoke(array $results, $callback = null)
    {
        $this->results = $results;
        $this->callback = $callback;
        return $this;
    }

    /**
     * Retrieve an external iterator.
     * @link https://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        $hits = collect();
        if ($this->results['hits']['total']) {
            $raw = collect($this->results['hits']['hits']);
            $models = $this->collectModels($raw);
            $eloquentHits = $this->getEloquentHits($raw, $models);
            $hits = $this->mergeHighlightsIntoModels($eloquentHits, $raw);
        }

        return new \ArrayIterator($hits);
    }

    private function collectModels($rawHits)
    {
        return collect($rawHits)
            ->groupBy('_source.__class_name')
            ->map(function ($results, $class) {
                $model = new $class;
                $builder = new Builder($model, '');
                if (! empty($this->callback)) {
                    $builder->query($this->callback);
                }
                /* @var Searchable $model */
                return $models = $model->getScoutModelsByIds(
                    $builder, $results->pluck('_id')->all()
                );
            })
            ->flatten()
            ->keyBy(function ($model) {
                return get_class($model).'::'.$model->getScoutKey();
            });
    }

    private function getEloquentHits($hits, $models)
    {
        return collect($hits)
            ->map(function ($hit) use ($models) {
                $key = $hit['_source']['__class_name'].'::'.$hit['_id'];

                return isset($models[$key]) ? $models[$key] : null;
            })->filter()->all();
    }

    private function mergeHighlightsIntoModels($eloquentHits, $raw)
    {
        return collect($eloquentHits)
            ->map(function (Model $eloquentHit) use ($raw) {
                $raw = collect($raw)
                    ->where('_source.__class_name', get_class($eloquentHit))
                    ->where('_source.id', $eloquentHit->id)
                    ->first();

                foreach ($raw['highlight'] ?? [] as $key => $highlight) {
                    if(in_array($key, ['customer.name', 'billing_address.name', 'shipping_address.name'])){
                        $key = 'name';
                    }

                    if(in_array($key, ['customer.email', 'billing_address.email', 'shipping_address.email'])){
                        $key = 'email';
                    }

                    if (! in_array($key, ['created_at', 'updated_at'])) {
                        $eloquentHit->setAttribute($key, $highlight[0]);
                    }
                }

                return $eloquentHit;
            })->all();
    }
}