<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 21.02.19
 * Time: 13:46
 */

namespace Matchish\ScoutElasticSearch\Payloads;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Traversable;


/**
 * @internal
 */
final class Bulk
{
    private $models;

    /**
     * Bulk constructor.
     * @param Collection $models
     */
    public function __construct(Collection $models)
    {
        $this->models = $models;
    }

    public function toArray(): array
    {
        $payload = ['body' => []];
        $payload = $this->models->reduce(
            function ($payload, $model) {
                /** @var Searchable|Model $model */
                if ($model::usesSoftDelete() && config('scout.soft_delete', false)) {
                    $model->pushSoftDeleteMetadata();
                }
                $payload['body'][] = [
                    'index' => [
                        '_index' => $model->searchableAs(),
                        '_id' => $model->getKey(),
                        '_type' => '_doc'
                    ]
                ];

                $payload['body'][] = array_merge(
                    $model->toSearchableArray(),
                    $model->scoutMetadata()
                );

                return $payload;
            }, $payload);
        return $payload;
    }

}
