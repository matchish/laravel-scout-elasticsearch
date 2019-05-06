<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elasticsearch\Client;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Get as GetAliasParams;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Delete as DeleteIndexParams;

/**
 * @internal
 */
final class CleanUp
{
    /**
     * @var Model
     */
    private $searchable;

    /**
     * @param Model $searchable
     */
    public function __construct(Model $searchable)
    {
        $this->searchable = $searchable;
    }

    public function handle(Client $elasticsearch): void
    {
        /** @var Searchable $searchable */
        $searchable = $this->searchable;
        $params = GetAliasParams::anyIndex($searchable->searchableAs());
        try {
            $response = $elasticsearch->indices()->getAliases($params->toArray());
        } catch (Missing404Exception $e) {
            $response = [];
        }
        foreach ($response as $indexName => $data) {
            foreach ($data['aliases'] as $alias => $config) {
                if (array_key_exists('is_write_index', $config) && $config['is_write_index']) {
                    $params = new DeleteIndexParams((string) $indexName);
                    $elasticsearch->indices()->delete($params->toArray());
                    continue 2;
                }
            }
        }
    }

    public function title(): string
    {
        return 'Clean up';
    }

    public function estimate(): int
    {
        return 1;
    }
}
