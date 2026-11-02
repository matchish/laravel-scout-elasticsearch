<?php

namespace Tests\Unit\ElasticSearch;

use App\Product;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Tests\TestCase;

class IndexTest extends TestCase
{
    public function test_creation_from_searchable()
    {
        $index = Index::fromSource(DefaultImportSourceFactory::from(Product::class));
        $this->assertEquals($index->name(), 'products_1525376494');
    }

    public function test_keeps_a_configured_gc_deletes(): void
    {
        config(['elasticsearch.indices.settings.default' => ['index.gc_deletes' => '24h']]);

        $index = Index::fromSource(DefaultImportSourceFactory::from(Product::class));

        $this->assertSame('24h', $index->config()['settings']['index.gc_deletes']);
    }

    public function test_keeps_a_configured_gc_deletes_without_the_index_prefix(): void
    {
        config(['elasticsearch.indices.settings.default' => ['gc_deletes' => '24h']]);

        $settings = Index::fromSource(DefaultImportSourceFactory::from(Product::class))->config()['settings'];

        $this->assertSame('24h', $settings['gc_deletes']);
        $this->assertArrayNotHasKey('index.gc_deletes', $settings, 'only the configured spelling is sent');
    }

    public function test_keeps_a_configured_gc_deletes_in_nested_form(): void
    {
        config(['elasticsearch.indices.settings.default' => ['index' => ['gc_deletes' => '24h']]]);

        $settings = Index::fromSource(DefaultImportSourceFactory::from(Product::class))->config()['settings'];

        $this->assertSame('24h', $settings['index']['gc_deletes']);
        $this->assertArrayNotHasKey('index.gc_deletes', $settings, 'only the configured spelling is sent');
    }

    public function test_keeps_deletes_for_12_hours_by_default(): void
    {
        config(['elasticsearch.indices.settings.default' => ['number_of_shards' => 1]]);

        $settings = Index::fromSource(DefaultImportSourceFactory::from(Product::class))->config()['settings'];

        $this->assertSame('12h', $settings['index.gc_deletes']);
    }
}

namespace Matchish\ScoutElasticSearch\ElasticSearch;

function time(): int
{
    return 1525376494;
}
