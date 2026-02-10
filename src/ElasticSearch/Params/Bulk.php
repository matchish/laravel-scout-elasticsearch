<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Params;

use Illuminate\Database\Eloquent\Model;
use Matchish\ScoutElasticSearch\Contracts\SearchableContract;

/**
 * @internal
 *
 * @phpstan-import-type SearchableModel from SearchableContract
 */
final class Bulk
{
    /**
     * @var array<string|int, Model>
     */
    private $indexDocs = [];

    /**
     * @var array<string|int, Model>
     */
    private $deleteDocs = [];

    /**
     * @param  array<Model>|object  $docs
     */
    public function delete($docs): void
    {
        if (is_iterable($docs)) {
            foreach ($docs as $doc) {
                $this->delete($doc);
            }
        } else {
            /** @var SearchableModel $docs */
            $this->deleteDocs[$docs->getScoutKey()] = $docs;
        }
    }

    /**
     * TODO: Add ability to extend payload without modifying the class.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $payload = ['body' => []];
        $payload = collect($this->indexDocs)->reduce(
            function ($payload, $model) {
                /** @var SearchableModel $model */
                if (config('scout.soft_delete', false) && $model::usesSoftDelete()) {
                    $model->pushSoftDeleteMetadata();
                }

                $attributes = $model->getAttributes();
                $routing = $attributes['routing'] ?? null;
                $scoutKey = $model->getScoutKey();

                $payload['body'][] = [
                    'index' => [
                        '_index' => $model->searchableAs(),
                        '_id' => $scoutKey,
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
                /** @var SearchableModel $model */
                $attributes = $model->getAttributes();
                $routing = $attributes['routing'] ?? null;
                $scoutKey = $model->getScoutKey();

                $payload['body'][] = [
                    'delete' => [
                        '_index' => $model->searchableAs(),
                        '_id' => $scoutKey,
                        'routing' => false === empty($routing) ? $routing : $scoutKey,
                    ],
                ];

                return $payload;
            }, $payload);

        /** @var array<mixed> */
        return $payload;
    }

    /**
     * @param  array<Model>|object  $docs
     */
    public function index($docs): void
    {
        if (is_iterable($docs)) {
            foreach ($docs as $doc) {
                $this->index($doc);
            }
        } else {
            /** @var SearchableModel $docs */
            $this->indexDocs[$docs->getScoutKey()] = $docs;
        }
    }
}
