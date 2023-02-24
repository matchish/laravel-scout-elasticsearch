<?php

namespace Tests\Integration\Engines;

use App\Product;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Create;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use stdClass;
use Tests\IntegrationTestCase;

final class ElasticSearchEngineTest extends IntegrationTestCase
{
    /**
     * @var ElasticSearchEngine
     */
    private $engine;

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
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params);
        $this->assertEquals($models->count(), $response['hits']['total']['value']);
        foreach ($response['hits']['hits'] as $doc) {
            $this->assertEquals('Scout', $doc['_source']['title']);
        }
    }

    public function test_update_throw_exception_on_elasticsearch_error()
    {
        $this->expectException(\Exception::class);
        $models = Product::all();
        $models->map(function ($model) {
            $model->price = 'bad format';

            return $model;
        });
        $index = Index::fromSource(DefaultImportSourceFactory::from(Product::class));
        $params = new Create(
            'products',
            $index->config()
        );
        $this->elasticsearch->indices()->create($params->toArray());
        $this->engine->update($models);
    }

    public function test_delete(): void
    {
        $models = Product::all();
        $this->engine->update($models);
        $this->refreshIndex('products');
        $shouldBeNotDeleted = $models->pop();
        $this->engine->delete($models);
        $this->refreshIndex('products');
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params)->asArray();
        $this->assertArrayHasKey('hits', $response);
        $this->assertArrayHasKey('total', $response['hits']);
        $this->assertArrayHasKey('value', $response['hits']['total']);
        $this->assertEquals(1, $response['hits']['total']['value']);
        foreach ($response['hits']['hits'] as $doc) {
            $this->assertEquals($shouldBeNotDeleted->getScoutKey(), $doc['_id']);
        }
    }

    public function test_flush(): void
    {
        $models = Product::all();
        $this->engine->update($models);
        $this->refreshIndex('products');
        $this->engine->flush(new Product());
        $this->refreshIndex('products');
        $params = [
            'index' => 'products',
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];
        $response = $this->elasticsearch->search($params)->asArray();
        $this->assertArrayHasKey('hits', $response);
        $this->assertArrayHasKey('total', $response['hits']);
        $this->assertArrayHasKey('value', $response['hits']['total']);
        $this->assertEquals(0, $response['hits']['total']['value']);
    }

    public function test_map_with_custom_key_name(): void
    {
        $this->app['config']['scout.key'] = 'custom_key';
        $models = Product::all();
        $keys = $models->map(function ($product) {
            return ['_id' => $product->getScoutKey(), '_source' => [
                '__class_name' => Product::class,
            ]];
        })->all();
        $results = ['hits' => ['hits' => $keys, 'total' => $models->count()]];
        $mappedModels = $this->engine->map(new Builder(new Product(), 'zonga'), $results, new Product());
        $this->assertEquals($models->map->id->all(), $mappedModels->map->id->all());
    }

    public function test_lazy_map(): void
    {
        $models = Product::all();
        $keys = $models->map(function ($product) {
            return ['_id' => $product->getScoutKey(), '_source' => [
                '__class_name' => Product::class,
            ]];
        })->all();
        $results = ['hits' => ['hits' => $keys, 'total' => $models->count()]];
        $mappedModels = $this->engine->lazyMap(new Builder(new Product(), 'zonga'), $results, new Product());
        $this->assertEquals($models->map->id->all(), $mappedModels->map->id->all());
    }

    public function test_lazy_map_for_mixed_search(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Not implemented for MixedSearch');
        $models = Product::all();
        $keys = $models->map(function ($product) {
            return ['_id' => $product->getScoutKey(), '_source' => [
                '__class_name' => Product::class,
            ]];
        })->all();
        $results = ['hits' => ['hits' => $keys, 'total' => $models->count()]];
        $mappedModels = $this->engine->lazyMap(new Builder(new Product(), 'zonga'), $results, new class extends Model
        {
            use Searchable;
        });
    }

    private function refreshIndex(string $index): void
    {
        $params = [
            'index' => $index,
        ];
        $this->elasticsearch->indices()->refresh($params);
    }
}
