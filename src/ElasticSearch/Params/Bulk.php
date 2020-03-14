<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Params;

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
     * TODO: Add ability to extend payload without modifying the class.
     *
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
                $routing = $model->routing;
                $scoutKey = $model->getScoutKey();
                $payload['body'][] = [
                    'index' => [
                        '_index' => $model->searchableAs(),
                        '_id' => $scoutKey,
                        '_type' => '_doc',
                        'routing' => false === empty($routing) ? $routing : $scoutKey,
                    ],
                ];

                $payload['body'][] = array_merge(
                    $model->toSearchableArray(),
                    $model->scoutMetadata(),
                    [
                        '__class_name' => get_class($model),
                    ]
                );

                return $payload;
            }, $payload);
        $payload = collect($this->deleteDocs)->reduce(
            function ($payload, $model) {
                $routing = $model->routing;
                $scoutKey = $model->getScoutKey();
                $payload['body'][] = [
                    'delete' => [
                        '_index' => $model->searchableAs(),
                        '_id' => $scoutKey,
                        '_type' => '_doc',
                        'routing' => false === empty($routing) ? $routing : $scoutKey,
                    ],
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
