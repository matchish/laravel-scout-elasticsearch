<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Library\HighlightedHitsIteratorAggregate;
use App\Product;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Artisan;
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use Tests\IntegrationTestCase;

/**
 * Covers the example in the "Working with results" section of the README.
 */
final class HighlightedResultsTest extends IntegrationTestCase
{
    public function test_custom_hits_iterator_aggregate_keeps_the_highlights(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $zongaAmount = rand(2, 5);
        factory(Product::class, $zongaAmount)->create(['title' => 'Zonga sneakers']);
        factory(Product::class, rand(1, 3))->create(['title' => 'Amazon Kindle Fire']);

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $this->app->bind(HitsIteratorAggregate::class, HighlightedHitsIteratorAggregate::class);

        $products = Product::search('zonga', function (Client $client, $body) {
            $highlight = new Highlight();
            $highlight->addField('title');

            $body->addHighlight($highlight);

            return $client->search([
                'index' => (new Product)->searchableAs(),
                'body' => $body->toArray(),
            ])->asArray();
        })->get();

        $this->assertCount($zongaAmount, $products);

        foreach ($products as $product) {
            $this->assertInstanceOf(Product::class, $product);
            $this->assertStringContainsString('<em>Zonga</em>', $product->highlight['title'][0]);
        }
    }
}
