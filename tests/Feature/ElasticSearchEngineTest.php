<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Product;
use Illuminate\Support\Facades\Artisan;
use Tests\IntegrationTestCase;

final class ElasticSearchEngineTest extends IntegrationTestCase
{
    public function test_pass_empty_response(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = random_int(1, 5);
        factory(Product::class, $productsAmount)->states(['iphone'])->create();
        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $results = Product::search('Quia', static function ($client, $body) {
            return $client->search(['index' => 'products', 'body' => $body->toArray()])->asArray();
        })->raw();

        $this->assertIsArray($results);
        $this->assertEmpty($results['hits']['hits']);
    }

    public function test_pass_with_response(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = random_int(1, 5);
        factory(Product::class, $productsAmount)->states(['iphone'])->create();
        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $results = Product::search('iphone', static function ($client, $body) {
            return $client->search(['index' => 'products', 'body' => $body->toArray()])->asArray();
        })->raw();

        $this->assertIsArray($results);
        $this->assertNotEmpty($results['hits']['hits']);
    }
}
