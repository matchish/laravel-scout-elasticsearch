<?php

declare(strict_types=1);

namespace Tests\Integration\Pipelines\Stages;

use App\Product;
use Tests\IntegrationTestCase;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Pipelines\Stages\PullFromSource;

final class PullFromSourceTest extends IntegrationTestCase
{
    public function test_put_all_entites_to_index(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new \stdClass()]],
        ]);
        $stage = new PullFromSource($this->elasticsearch);
        $stage([Index::fromSearchable(new Product()), new Product()]);
        $this->elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']);
    }

    public function test_dont_put_entities_if_no_entities_in_collection(): void
    {
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new \stdClass()]],
        ]);
        $stage = new PullFromSource($this->elasticsearch);
        $stage([Index::fromSearchable(new Product()), new Product()]);
        $this->elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals(0, $response['hits']['total']);
    }

    public function test_put_all_to_index_if_amount_of_entities_more_than_chunk_size(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = 20;
        $this->app['config']->set('scout.chunk.searchable', 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new \stdClass()]],
        ]);
        $stage = new PullFromSource($this->elasticsearch);
        $stage([Index::fromSearchable(new Product()), new Product()]);
        $this->elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']);
    }

    public function test_push_soft_delete_meta_data()
    {
        $this->app['config']['scout.soft_delete'] = true;

        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new \stdClass()]],
        ]);
        $stage = new PullFromSource($this->elasticsearch);
        $stage([Index::fromSearchable(new Product()), new Product()]);
        $this->elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals(0, $response['hits']['hits'][0]['_source']['__soft_deleted']);
    }
}
