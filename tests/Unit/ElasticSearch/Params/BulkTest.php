<?php

namespace Tests\Unit\ElasticSearch\Params;

use App\Product;
use Illuminate\Database\Eloquent\Model;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
use Tests\TestCase;

class BulkTest extends TestCase
{
    protected function tearDown(): void
    {
        // The flag is a global static, so reset it to not affect other tests.
        Model::preventAccessingMissingAttributes(false);

        parent::tearDown();
    }

    public function test_delete()
    {
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->delete($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [['delete' => ['_index' => 'products', '_id' => 2, 'routing' => 2]]],
        ], $params);
    }

    public function test_delete_with_custom_key_name()
    {
        $this->app['config']['scout.key'] = 'title';
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->delete($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [['delete' => ['_index' => 'products', '_id' => 'Scout', 'routing' => 'Scout']]],
        ], $params);
    }

    public function test_index()
    {
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->index($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [
                ['index' => ['_index' => 'products', '_id' => 2, 'routing' => 2]],
                ['title' => 'Scout', 'id' => 2, '__class_name' => 'App\Product'],
            ],
        ], $params);
    }

    public function test_index_with_custom_key_name()
    {
        $this->app['config']['scout.key'] = 'title';
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->index($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [
                ['index' => ['_index' => 'products', '_id' => 'Scout', 'routing' => 'Scout']],
                ['title' => 'Scout', 'id' => 2, '__class_name' => 'App\Product'],
            ],
        ], $params);
    }

    public function test_index_model_without_routing_attribute()
    {
        Model::preventAccessingMissingAttributes();
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        // Only a model loaded from the database triggers the strict check.
        $product->exists = true;
        $bulk->index($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [
                ['index' => ['_index' => 'products', '_id' => 2, 'routing' => 2]],
                ['title' => 'Scout', 'id' => 2, '__class_name' => 'App\Product'],
            ],
        ], $params);
    }

    public function test_delete_model_without_routing_attribute()
    {
        Model::preventAccessingMissingAttributes();
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        // Only a model loaded from the database triggers the strict check.
        $product->exists = true;
        $bulk->delete($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [['delete' => ['_index' => 'products', '_id' => 2, 'routing' => 2]]],
        ], $params);
    }

    public function test_push_soft_delete_meta_data()
    {
        $this->app['config']['scout.soft_delete'] = true;
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->index($product);
        $params = $bulk->toArray();
        $this->assertEquals([
            'body' => [
                ['index' => ['_index' => 'products', '_id' => 2, 'routing' => 2]],
                ['title' => 'Scout', '__soft_deleted' => 0, 'id' => 2, '__class_name' => 'App\Product'],
            ],
        ], $params);
    }
}
