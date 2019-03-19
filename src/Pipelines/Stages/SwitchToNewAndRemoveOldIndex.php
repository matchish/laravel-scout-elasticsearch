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
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Update;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Get;

/**
 * @internal
 */
final class SwitchToNewAndRemoveOldIndex
{
    /**
     * @var Client
     */
    private $elasticsearch;

    public function __construct(Client $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    public function __invoke($payload)
    {
        [$index, $source] = $payload;

        $params = Get::anyIndex($source->searchableAs());
        $response = $this->elasticsearch->indices()->getAliases($params->toArray());

        $params = new Update();
        foreach ($response as $indexName => $alias) {
            if ($indexName != $index->name()) {
                $params->removeIndex($indexName);
            } else {
                $params->add((string)$indexName, $source->searchableAs());
            }
        }
        $this->elasticsearch->indices()->updateAliases($params->toArray());

        return [$index, $source];
    }
}
