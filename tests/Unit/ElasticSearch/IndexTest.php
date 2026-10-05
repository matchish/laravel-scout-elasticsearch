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

    /**
     * @group parallel-import-regressions
     */
    public function test_explicit_gc_deletes_setting_is_preserved(): void
    {
        config(['elasticsearch.indices.settings.default' => ['index.gc_deletes' => '24h']]);

        $index = Index::fromSource(DefaultImportSourceFactory::from(Product::class));

        $this->assertSame('24h', $index->config()['settings']['index.gc_deletes']);
    }

    public function test_gc_deletes_without_the_index_prefix_is_preserved(): void
    {
        config(['elasticsearch.indices.settings.default' => ['gc_deletes' => '24h']]);

        $settings = Index::fromSource(DefaultImportSourceFactory::from(Product::class))->config()['settings'];

        $this->assertSame('24h', $settings['gc_deletes']);
        $this->assertArrayNotHasKey('index.gc_deletes', $settings, 'a second spelling would conflict with the first');
    }

    public function test_nested_gc_deletes_setting_is_preserved(): void
    {
        config(['elasticsearch.indices.settings.default' => ['index' => ['gc_deletes' => '24h']]]);

        $settings = Index::fromSource(DefaultImportSourceFactory::from(Product::class))->config()['settings'];

        $this->assertSame('24h', $settings['index']['gc_deletes']);
        $this->assertArrayNotHasKey('index.gc_deletes', $settings, 'a second spelling would conflict with the first');
    }

    public function test_gc_deletes_default_applies_when_settings_do_not_define_it(): void
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
