<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 20.03.19
 * Time: 12:39
 */

namespace Matchish\ScoutElasticSearch\ElasticSearch;


use Illuminate\Database\Eloquent\Collection;

final class DefaultSearchResults extends Collection implements SearchResults
{
    /**
     * @var array
     */
    private $results;

    /**
     * @param array $results
     */
    public function __construct(array $results)
    {
        if (array_key_exists('hits', $results)) {
            $this->results = $results;
            $items = $this->results['hits']['hits'];
        } else {
            $items = $results;
        }
        parent::__construct($items);
    }

    public function total(): int
    {
        return $this->results['hits']['total'];
    }

    public function mapTo($model, $builder): SearchResults
    {
        $keys = $this->pluck('_id')->values()->all();

        $models = $model->getScoutModelsByIds(
            $builder, $keys
        )->keyBy(function ($model) {
            return $model->getScoutKey();
        });
        return $this->map(function ($hit) use ($models) {
            return isset($models[$hit['_id']]) ? $models[$hit['_id']] : null;
        })->filter()->values();
    }

}