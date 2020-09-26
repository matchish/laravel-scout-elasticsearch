<?php

namespace Tests\Unit\ElasticSearch;

use App\Product;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Tests\TestCase;

class IndexTest extends TestCase
{
    public function test_creation_from_searchable()
    {
        $index = DefaultImportSourceFactory::from(Product::class)->defineIndex();
        $this->assertEquals($index->name(), 'products_1525376494');
    }
}

namespace Matchish\ScoutElasticSearch\Searchable;

function time(): int
{
    return 1525376494;
}
