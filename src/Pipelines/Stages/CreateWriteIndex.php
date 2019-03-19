<?php

namespace Matchish\ScoutElasticSearch\Pipelines\Stages;


use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearch\DefaultAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Create;
use Matchish\ScoutElasticSearch\ElasticSearch\WriteAlias;

/**
 * @internal
 */
final class CreateWriteIndex
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

        $index->addAlias(new WriteAlias(new DefaultAlias($source->searchableAs())));

        $params = new Create(
            $index->name(),
            $index->config()
        );

        $this->elasticsearch->indices()->create($params->toArray());

        return [$index, $source];
    }
}
