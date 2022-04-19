<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Product;
use Illuminate\Support\Facades\Artisan;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Refresh;
use Tests\IntegrationTestCase;

final class FlushCommandTest extends IntegrationTestCase
{
    public function test_clear_index(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);
        $params = new Bulk();
        $params->index(Product::all());
        $this->elasticsearch->bulk($params->toArray());
        $this->elasticsearch->indices()->refresh((new Refresh('products'))->toArray());
        Artisan::call('scout:flush');

        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ];

        $response = $this->elasticsearch->search($params);
        $this->assertEquals(0, $response['hits']['total']['value']);
    }
}
