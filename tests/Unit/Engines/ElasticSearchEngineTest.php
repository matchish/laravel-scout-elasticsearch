<?php

namespace Tests\Unit\Engines;

use App\Product;
use Mockery as m;
use Tests\TestCase;
use Elasticsearch\Client;
use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\Search;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;

class ElasticSearchEngineTest extends TestCase
{
    public function test_map_ids()
    {
        $sut = new ElasticSearchEngine(app(Client::class));
        $ids = $sut->mapIds(['hits' => ['hits' => [['_id' => 1], ['_id' => 15]]]]);

        $this->assertEquals([1, 15], $ids->all());
    }

    public function test_pass_client_to_callback()
    {
        $client = m::mock(Client::class);
        $engine = new ElasticSearchEngine($client);
        $query = 'zonda';
        $client->shouldReceive('search')->once()->withNoArgs();
        $builder = new Builder(new Product(), $query, function ($client, $query) {
            return $client->search();
        });
        $engine->search($builder);
    }

    public function test_should_not_call_bulk_when_empty_models()
    {
        $client = m::mock(Client::class);
        $client->shouldNotReceive('bulk');

        $engine = new ElasticSearchEngine($client);

        $engine->update([
            new Product([])
        ]);
    }

    public function test_should_call_bulk_when_not_empty_models()
    {
        $client = m::mock(Client::class);
        $client->shouldReceive('bulk');
        $engine = new ElasticSearchEngine($client);

        $engine->update([
            new Product(['title' => 'Scout'])
        ]);
    }

    public function test_pass_search_builder_to_callback()
    {
        $client = m::mock(Client::class);
        $engine = new ElasticSearchEngine($client);
        $client->shouldReceive('search')->once()->with(m::type(Search::class));
        $query = 'zonda';
        $builder = new Builder(new Product(), $query, function ($client, $query) {
            return $client->search($query);
        });
        $engine->search($builder);
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
