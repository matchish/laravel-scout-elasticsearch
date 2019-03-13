<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Product;
use Elasticsearch\Client;
use Illuminate\Support\Facades\Artisan;
use Tests\IntegrationTestCase;

final class FlushCommandTest extends IntegrationTestCase
{
    public function testClearsIndex(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:flush');

        $elasticsearch = $this->app->make(Client::class);
        $params = [
            "index" => (new Product())->searchableAs(),
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];

        /** @var Client $elasticseearch */
        $response = $elasticsearch->search($params);
        $this->assertEquals(0, $response['hits']['total']);
    }
}

