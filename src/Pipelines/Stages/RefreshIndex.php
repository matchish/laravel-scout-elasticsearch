<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 14.03.19
 * Time: 13:00
 */

namespace Matchish\ScoutElasticSearch\Pipelines\Stages;


use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Refresh;

/**
 * @internal
 */
final class RefreshIndex
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

        $params = new Refresh($index->name());
        $this->elasticsearch->indices()->refresh($params->toArray());

        return [$index, $source];
    }
}
