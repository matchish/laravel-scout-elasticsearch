<?php
declare(strict_types=1);

namespace Tests\Feature;

use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\RefreshIndex;
use Tests\IntegrationTestCase;

final class RefreshIndexTest extends IntegrationTestCase
{

    public function testRefreshIndex(): void
    {
        $elasticsearch = $this->app->make(Client::class);
        $elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new \stdClass()]]
        ]);
        $elasticsearch->bulk(['body' => [
            ['index' => [
                '_index' => 'products',
                '_id' => 'id',
                '_type' => '_doc'
            ]],
            [
                'id' => 1,
                'title' => 'Scout'
            ]]
        ]);

        $job = new RefreshIndex(new Index('products'));
        $job->handle($elasticsearch);

        $params = [
            "index" => 'products',
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];
        $response = $elasticsearch->search($params);
        $this->assertEquals(1, $response['hits']['total']);
    }

}
