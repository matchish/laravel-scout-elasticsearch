<?php

namespace Tests\Unit\Engines;

use App\Product;
use Elastic\Elasticsearch\Client;
use Laravel\Scout\Builder;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;
use Tests\TestCase;

class ElasticSearchEngineTest extends TestCase
{
    public function test_map_ids()
    {
        $sut = new ElasticSearchEngine(app(Client::class));
        $ids = $sut->mapIds(['hits' => ['hits' => [['_id' => 1], ['_id' => 15]]]]);

        $this->assertEquals([1, 15], $ids->all());
    }

    public function test_pass_query_to_callback_before_executing()
    {
        $builder = new Builder(new Product(), 'zonga');
        $spy = new \stdClass();
        $builder->query(function ($query) use ($spy) {
            $spy->executed = true;

            return $query;
        });
        $engine = new ElasticSearchEngine(app(Client::class));
        $engine->map($builder, [
            'hits' => [
                'hits' => [
                    [
                        '_id' => 1, '_source' => [
                            '__class_name' => Product::class,
                        ], ],
                    [
                        '_id' => 2, '_source' => [
                            '__class_name' => Product::class,
                        ], ],
                ],
                'total' => 2,
            ], ], new Product());
        $this->assertTrue($spy->executed);
    }
}
