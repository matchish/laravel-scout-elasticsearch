<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs\Stages;

use App\Product;
use Illuminate\Support\Carbon;
use Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSource;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use stdClass;
use Tests\IntegrationTestCase;

final class PullFromSourceTest extends IntegrationTestCase
{
    /**
     * The sequential import reads a chunk and writes it a moment later.
     * A live change made in between, in the same second, must win.
     */
    public function test_keeps_a_live_update_made_in_the_same_second(): void
    {
        Carbon::setTestNow('2000-01-01 00:00:00');

        try {
            $product = $this->productInLiveIndex();
            $updated = false;
            Product::retrieved(function (Product $model) use ($product, &$updated) {
                if (! $updated && $model->getKey() === $product->getKey()) {
                    $updated = true;
                    $product->update(['title' => 'live title']);
                }
            });

            PullFromSource::chunked(DefaultImportSourceFactory::from(Product::class))->handle();

            $document = $this->elasticsearch->get([
                'index' => 'products',
                'id' => (string) $product->getKey(),
            ])->asArray();
            $this->assertSame('live title', $document['_source']['title'], 'the import must not roll back the live update');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_does_not_bring_back_a_row_deleted_in_the_same_second(): void
    {
        Carbon::setTestNow('2000-01-01 00:00:00');

        try {
            $product = $this->productInLiveIndex();
            $deleted = false;
            Product::retrieved(function (Product $model) use ($product, &$deleted) {
                if (! $deleted && $model->getKey() === $product->getKey()) {
                    $deleted = true;
                    $product->delete();
                }
            });

            PullFromSource::chunked(DefaultImportSourceFactory::from(Product::class))->handle();

            $this->assertFalse(
                $this->elasticsearch->exists(['index' => 'products', 'id' => (string) $product->getKey()])->asBool(),
                'a product deleted while its chunk was in memory must not come back'
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    private function productInLiveIndex(): Product
    {
        // Boot the model while the dispatcher is in place, so Scout
        // registers its observer; then seed without syncing.
        new Product();
        /** @var Product $product */
        $product = Product::withoutEvents(function () {
            return factory(Product::class)->create(['title' => 'old title']);
        });
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new stdClass()]],
        ]);

        return $product;
    }

    public function test_put_all_entities_to_index(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new stdClass()]],
        ]);
        $stage = new PullFromSource(DefaultImportSourceFactory::from(Product::class));
        $stage->handle();
        $this->elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']['value']);
    }

    public function test_dont_put_entities_if_no_entities_in_collection(): void
    {
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new stdClass()]],
        ]);
        $stage = new PullFromSource(DefaultImportSourceFactory::from(Product::class));
        $stage->handle();
        $this->elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals(0, $response['hits']['total']['value']);
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
            'body' => ['aliases' => ['products' => new stdClass()]],
        ]);
        $stage = new PullFromSource(DefaultImportSourceFactory::from(Product::class));
        $stage->handle();
        $this->elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals($productsAmount, $response['hits']['total']['value']);
    }

    public function test_pull_soft_delete_meta_data()
    {
        $this->app['config']['scout.soft_delete'] = true;

        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = rand(1, 5);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new stdClass()]],
        ]);
        $stage = new PullFromSource(DefaultImportSourceFactory::from(Product::class));
        $stage->handle();
        $this->elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals(0, $response['hits']['hits'][0]['_source']['__soft_deleted']);
    }

    public function test_pull_soft_deleted()
    {
        $this->app['config']['scout.soft_delete'] = true;

        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = 3;

        factory(Product::class, $productsAmount)->create();

        Product::limit(1)->get()->first()->delete();

        Product::setEventDispatcher($dispatcher);
        $this->elasticsearch->indices()->create([
            'index' => 'products_index',
            'body' => ['aliases' => ['products' => new stdClass()]],
        ]);
        $stage = PullFromSource::chunked(DefaultImportSourceFactory::from(Product::class));
        $stage->handle();
        $this->elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals(3, $response['hits']['total']['value']);
    }

    public function test_no_searchables_no_chunks()
    {
        $stage = PullFromSource::chunked(DefaultImportSourceFactory::from(Product::class));

        $this->assertEquals(null, $stage);
    }

    public function test_chunked_pull_only_one_page()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $productsAmount = 5;

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);

        $chunks = PullFromSource::chunked(DefaultImportSourceFactory::from(Product::class));
        $chunks->handle();
        $this->elasticsearch->indices()->refresh([
            'index' => 'products',
        ]);
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);

        $this->assertEquals(3, $response['hits']['total']['value']);
    }
}
