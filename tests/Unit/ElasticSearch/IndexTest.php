<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 12.03.19
 * Time: 8:29
 */

namespace Tests\Unit\ElasticSearch;

use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Tests\TestCase;

class IndexTest extends \Orchestra\Testbench\TestCase
{

    public function testName()
    {
        $index = new Index('products');
        $this->assertEquals($index->name(), 'products_1525376494');
    }

    public function testAlias()
    {
        $index = new Index('products');
        $this->assertEquals($index->alias(), 'products');
    }
}

namespace Matchish\ScoutElasticSearch\ElasticSearch;
function time():int {
    return 1525376494;
}
