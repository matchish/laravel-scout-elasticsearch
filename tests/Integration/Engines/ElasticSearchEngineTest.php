<?php
namespace Tests\Integration\Engines;

use App\Product;
use Laravel\Scout\Builder;
use Matchish\ScoutElasticSearch\ElasticSearch\SearchResults;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;
use Tests\IntegrationTestCase;

final class ElasticSearchEngineTest extends IntegrationTestCase
{

    /**
     * @var ElasticSearchEngine
     */
    private $engine;

    /**
     *
     */
    public function setUp(): void
    {
        parent::setUp();
        $dispatcher = Product::getEventDispatcher();

        Product::unsetEventDispatcher();

        $productsAmount = rand(3, 10);

        factory(Product::class, $productsAmount)->create();

        Product::setEventDispatcher($dispatcher);
        $this->engine = new ElasticSearchEngine($this->elasticsearch);
    }

    /**
     *
     */
    public function test_update()
    {
        $models = Product::all();
        $models->map(function ($model) {
            $model->title = 'Scout';
            return $model;
        });
        $this->engine->update($models);
        $this->refreshIndex('products');
        $params = [
            "index" => 'products',
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals($models->count(), $response['hits']['total']);
        foreach ($response['hits']['hits'] as $doc) {
            $this->assertEquals('Scout', $doc['_source']['title']);
        }
    }

    /**
     *
     */
    public function test_delete()
    {
        $models = Product::all();
        $this->engine->update($models);
        $this->refreshIndex('products');
        $shouldBeNotDeleted = $models->pop();
        $this->engine->delete($models);
        $this->refreshIndex('products');
        $params = [
            "index" => 'products',
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals(1, $response['hits']['total']);
        foreach ($response['hits']['hits'] as $doc) {
            $this->assertEquals($shouldBeNotDeleted->getScoutKey(), $doc['_id']);
        }
    }

    public function test_flush()
    {
        $models = Product::all();
        $this->engine->update($models);
        $this->refreshIndex('products');
        $this->engine->flush(new Product());
        $this->refreshIndex('products');
        $params = [
            "index" => 'products',
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals(0, $response['hits']['total']);
    }

    public function test_map_with_custom_key_name()
    {
        $this->app['config']['scout.key'] = 'custom_key';
        $models = Product::all();
        $keys = $models->map(function ($product) {
            return ['_id' => $product->getScoutKey()];
        })->all();
        $mappedModels = $this->engine->map(new Builder(new Product(), 'zonga'), new SearchResults($keys), new Product());
        $this->assertEquals($models->map->id->all(), $mappedModels->map->id->all());
    }

    private function refreshIndex(string $index): void
    {
        $params = [
            "index" => $index,
        ];
        $this->elasticsearch->indices()->refresh($params);
    }
}
