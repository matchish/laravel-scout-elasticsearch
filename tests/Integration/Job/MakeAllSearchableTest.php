<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Product;
use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\Jobs\MakeAllSearchable;
use Tests\IntegrationTestCase;

final class MakeAllSearchableTest extends IntegrationTestCase
{
    public function testPutAllEntitesToIndex(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);
        $elasticsearch = $this->app->make(Client::class);
        $elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new \stdClass()]]
        ]);
        $job = new MakeAllSearchable(Product::class);
        $job->handle();
        $elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            "index" => 'products',
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];
        $response = $elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']);
    }

    public function testDontPutEntitiesIfNoEntitiesInCollection(): void
    {
        $elasticsearch = $this->app->make(Client::class);
        $elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new \stdClass()]]
        ]);
        $job = new MakeAllSearchable(Product::class);
        $job->handle();
        $elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            "index" => 'products',
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];
        $response = $elasticsearch->search($params);
        $this->assertEquals(0, $response['hits']['total']);
    }

    public function testPutAllToIndexIfAmountOfEntitiesMoreThanChunkSize(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = 20;
        $this->app['config']->set('scout.chunk.searchable', 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);
        $elasticsearch = $this->app->make(Client::class);
        $elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new \stdClass()]]
        ]);
        $job = new MakeAllSearchable(Product::class);
        $job->handle();
        $elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            "index" => 'products',
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];
        $response = $elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']);
    }
}
