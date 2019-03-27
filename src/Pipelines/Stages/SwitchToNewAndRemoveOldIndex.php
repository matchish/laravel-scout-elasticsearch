<?php

namespace Matchish\ScoutElasticSearch\Pipelines\Stages;

use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Get;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Update;

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
                $params->add((string) $indexName, $source->searchableAs());
            }
        }
        $this->elasticsearch->indices()->updateAliases($params->toArray());

        return [$index, $source];
    }
}
