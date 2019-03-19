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
        ], $payload);
    }
}
