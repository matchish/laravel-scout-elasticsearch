<?php
namespace Tests\Unit\ElasticSearch\Params;

use App\Product;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
use Tests\TestCase;

class BulkTest extends TestCase
{

    public function testDelete()
    {
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->delete($product);
        $payload = $bulk->toArray();

        $this->assertEquals([
            'body' => [['delete' => ['_index' => 'products', '_type' => '_doc', '_id' => 2]]]
        ], $payload);
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
            'body' => [['delete' => ['_index' => 'products', '_type' => '_doc', '_id' => 'Scout']]]
        ], $params);
    }

    public function testIndex()
    {
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->index($product);
        $payload = $bulk->toArray();

        $this->assertEquals([
            'body' => [
                ['index' => ['_index' => 'products', '_type' => '_doc', '_id' => 2]],
                ['title' => 'Scout', 'id' => 2],
            ]
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
                ['title' => 'Scout', 'id' => 2],
            ]
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
                ['title' => 'Scout', '__soft_deleted' => 0, 'id' => 2],
            ]
        ], $params);
    }
}
