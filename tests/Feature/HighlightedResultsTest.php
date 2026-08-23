<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Library\HitsWithRawDataIteratorAggregate;
use App\Product;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Artisan;
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use Tests\IntegrationTestCase;

/**
 * Covers the examples in the "Working with results" section of the README.
 */
final class HighlightedResultsTest extends IntegrationTestCase
{
    private int $zongaAmount;

    public function setUp(): void
    {
        parent::setUp();

        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $this->zongaAmount = rand(2, 5);
        factory(Product::class, $this->zongaAmount)->create(['title' => 'Zonga sneakers']);
        factory(Product::class, rand(1, 3))->create(['title' => 'Amazon Kindle Fire']);

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');
    }

    public function test_elastic_params_trait_gives_score_and_highlight(): void
    {
        $products = $this->searchWithHighlight();

        $this->assertCount($this->zongaAmount, $products);

        foreach ($products as $product) {
            $this->assertGreaterThan(0, $product->getElasticsearchScore());
            $this->assertStringContainsString(
                '<em>Zonga</em>',
                $product->getElasticsearchHighlight()['title'][0]
            );
        }
    }

    public function test_custom_hits_iterator_aggregate_keeps_the_whole_hit(): void
    {
        $this->app->bind(HitsIteratorAggregate::class, HitsWithRawDataIteratorAggregate::class);

        $products = $this->searchWithHighlight();

        $this->assertCount($this->zongaAmount, $products);

        foreach ($products as $product) {
            $this->assertInstanceOf(Product::class, $product);
            // The hit carries the real index name, not the alias.
            $this->assertStringStartsWith('products_', $product->hit['_index']);
            $this->assertEquals($product->getScoutKey(), $product->hit['_id']);

            // The custom class fills the trait itself.
            $this->assertGreaterThan(0, $product->getElasticsearchScore());
            $this->assertStringContainsString(
                '<em>Zonga</em>',
                $product->getElasticsearchHighlight()['title'][0]
            );
        }
    }

    private function searchWithHighlight()
    {
        return Product::search('zonga', function (Client $client, $body) {
            $highlight = new Highlight();
            $highlight->addField('title');

            $body->addHighlight($highlight);

            return $client->search([
                'index' => (new Product)->searchableAs(),
                'body' => $body->toArray(),
            ])->asArray();
        })->get();
    }
}
