<?php

namespace Tests\Unit\ElasticSearch\Params;

use App\Product;
use Tests\TestCase;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;

class BulkTest extends TestCase
{
    public function test_delete()
    {
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->delete($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [['delete' => ['_index' => 'products', '_type' => '_doc', '_id' => 2]]],
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
            'body' => [['delete' => ['_index' => 'products', '_type' => '_doc', '_id' => 'Scout']]],
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
                ['index' => ['_index' => 'products', '_type' => '_doc', '_id' => 2]],
                ['title' => 'Scout', 'id' => 2, '__class_name' => 'App\Product'],
            ],
        ], $params);
    }

    public function test_index_empty_model()
    {
        $bulk = new Bulk();
        $product = new Product([]);
        $bulk->index($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [],
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
                ['index' => ['_index' => 'products', '_type' => '_doc', '_id' => 'Scout']],
                ['title' => 'Scout', 'id' => 2, '__class_name' => 'App\Product'],
            ],
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
                ['index' => ['_index' => 'products', '_type' => '_doc', '_id' => 2]],
                ['title' => 'Scout', '__soft_deleted' => 0, 'id' => 2, '__class_name' => 'App\Product'],
            ],
        ], $params);
    }
}
