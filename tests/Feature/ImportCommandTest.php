<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Product;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

final class ImportCommandTest extends TestCase
{
    public function testItImportsEntites(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');
        $elasticsearch = $this->app->make(Client::class);
        $params = [
            "index" => (new Product())->searchableAs(),
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];
        $response = $elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']);
    }

    public function testItImportsEntitesInQueue(): void
    {
        $this->app['config']->set('scout.queue', ['connection' => 'sync', 'queue' => 'scout']);

        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');
        $elasticsearch = $this->app->make(Client::class);
        $params = [
            "index" => (new Product())->searchableAs(),
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];
        $response = $elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']);
    }

    public function testItImportsAllPages(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = 1000;

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');
        $elasticsearch = $this->app->make(Client::class);
        $params = [
            "index" => (new Product())->searchableAs(),
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];
        $response = $elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']);
    }

    public function testItRemovesOldIndexAfterSwitchingToNew(): void
    {
        /** @var Client $elasticsearch */
        $elasticsearch = $this->app->make(Client::class);
        $params = [
            "index" => 'products_old',
            "body" => [
                "aliases" => [(new Product())->searchableAs() => new \stdClass()],
                'settings' => [
                    "number_of_shards" => 1,
                    "number_of_replicas" => 0,
                ]
            ]
        ];
        $elasticsearch->indices()->create($params);
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $this->assertFalse($elasticsearch->indices()->exists(['index' => 'products_old']), "Old index must be deleted");
    }
}
