<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 14.03.19
 * Time: 13:00
 */

namespace Matchish\ScoutElasticSearch\Pipelines\Stages;


use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Delete;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Get;

/**
 * @internal
 */
final class CleanUp
{
    /**
     * @var Client
     */
    private $elasticsearch;

    /**
     * RemoveWriteIndex constructor.
     */
    public function __construct(Client $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    public function __invoke($payload): array
    {
        [$index, $source] = $payload;
        $params = Get::anyIndex($source->searchableAs());
        try {
            $response = $this->elasticsearch->indices()->getAliases($params->toArray());
        } catch (Missing404Exception $e) {
            $response = [];
        }
        foreach ($response as $indexName => $data) {
            foreach ($data['aliases'] as $alias => $config) {
                if (array_key_exists('is_write_index', $config) && $config['is_write_index']) {
                    $params = new Delete((string)$indexName);
                    $this->elasticsearch->indices()->delete($params->toArray());
                    continue 2;
                }
            }
        }
        return [$index, $source];
    }
}
