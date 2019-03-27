<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 12.03.19
 * Time: 8:29.
 */

namespace Tests\Unit\ElasticSearch;

use App\Product;
use Tests\TestCase;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;

class IndexTest extends TestCase
{
    public function test_creation_from_searchable()
    {
        $index = Index::fromSearchable(new Product());
        $this->assertEquals($index->name(), 'products_1525376494');
    }
}

namespace Matchish\ScoutElasticSearch\ElasticSearch;

function time():int
{
    return 1525376494;
}
