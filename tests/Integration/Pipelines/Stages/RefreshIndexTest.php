<?php

declare(strict_types=1);

namespace Tests\Integration\Pipelines\Stages;

use App\Product;
use Tests\IntegrationTestCase;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Pipelines\Stages\RefreshIndex;

final class RefreshIndexTest extends IntegrationTestCase
{
    public function test_refresh_index(): void
    {
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new \stdClass()]],
        ]);
        $this->elasticsearch->bulk(['body' => [
            ['index' => [
                '_index' => 'products',
                '_id' => 'id',
                '_type' => '_doc',
            ]],
            [
                'id' => 1,
                'title' => 'Scout',
            ], ],
        ]);

        $stage = new RefreshIndex($this->elasticsearch);
        $stage([new Index('products_index'), new Product()]);

        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals(1, $response['hits']['total']);
    }
}
