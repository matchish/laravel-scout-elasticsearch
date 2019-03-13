<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 12.03.19
 * Time: 15:49
 */

namespace Tests\Integration\Engines;

use App\Product;
use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;
use Tests\TestCase;

/**
 * Class ElasticSearchEngineTest
 * @package Tests\Integration\Engines
 */
final class ElasticSearchEngineTest extends TestCase
{

    /**
     * @var ElasticSearchEngine
     */
    private $engine;
    /**
     * @var Client
     */
    private $elasticsearch;

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
        $this->elasticsearch = $this->app->make(Client::class);
        $this->engine = new ElasticSearchEngine($this->elasticsearch);
    }

    /**
     *
     */
    public function testUpdate()
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
    public function testDelete()
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

    /**
     *
     */
    public function testMapIds()
    {

    }

    /**
     *
     */
    public function testFlush()
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

    /**
     *
     */
    public function testSearch()
    {

    }

    /**
     *
     */
    public function testMap()
    {

    }

    /**
     *
     */
    public function testGetTotalCount()
    {

    }

    /**
     *
     */
    public function testPaginate()
    {

    }

    /**
     * @param string $index
     * @return void
     */
    private function refreshIndex(string $index): void
    {
        $params = [
            "index" => $index,
        ];
        $this->elasticsearch->indices()->refresh($params);
    }
}
