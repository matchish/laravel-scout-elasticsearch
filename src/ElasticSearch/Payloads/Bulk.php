<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 21.02.19
 * Time: 13:46
 */

namespace Matchish\ScoutElasticSearch\ElasticSearch\Payloads;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Traversable;


/**
 * @internal
 */
final class Bulk
{
    /**
     * @var array
     */
    private $indexDocs = [];


    /**
     * @var array
     */
    private $deleteDocs = [];

    /**
     * @param array|object $docs
     */
    public function delete($docs): void
    {
        if (is_iterable($docs)) {
            foreach ($docs as $doc) {
                $this->delete($doc);
            }
        } else {
            $this->deleteDocs[$docs->getScoutKey()] = $docs;
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $payload = ['body' => []];
        $payload = collect($this->indexDocs)->reduce(
            function ($payload, $model) {
                if ($model::usesSoftDelete() && config('scout.soft_delete', false)) {
                    $model->pushSoftDeleteMetadata();
                }
                $payload['body'][] = [
                    'index' => [
                        '_index' => $model->searchableAs(),
                        '_id' => $model->getScoutKey(),
                        '_type' => '_doc'
                    ]
                ];

                $payload['body'][] = array_merge(
                    $model->toSearchableArray(),
                    $model->scoutMetadata()
                );

                return $payload;
            }, $payload);
        $payload = collect($this->deleteDocs)->reduce(
            function ($payload, $model) {
                $payload['body'][] = [
                    'delete' => [
                        '_index' => $model->searchableAs(),
                        '_id' => $model->getScoutKey(),
                        '_type' => '_doc'
                    ]
                ];

                return $payload;
            }, $payload);
        return $payload;
    }

    /**
     * @param array|object $docs
     */
    public function index($docs): void
    {
        if (is_iterable($docs)) {
            foreach ($docs as $doc) {
                $this->index($doc);
            }
        } else {
            $this->indexDocs[$docs->getScoutKey()] = $docs;
        }
    }

}