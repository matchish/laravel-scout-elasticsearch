<?php

namespace Tests\Unit;

use Laravel\Scout\Searchable;
use Matchish\ScoutElasticSearch\Mixed;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MixedTest extends TestCase
{
    public function test_search_signature()
    {
        $searchable = new ReflectionClass(Searchable::class);
        $searchableParameters = $searchable->getMethod('search')->getParameters();
        $searchableDoc = $searchable->getMethod('search')->getDocComment();
        $mixed = new ReflectionClass(Mixed::class);
        $mixedParameters = $mixed->getMethod('search')->getParameters();
        $mixedDoc = $mixed->getMethod('search')->getDocComment();

        $this->assertEquals($searchableParameters, $mixedParameters);
        $this->assertEquals($searchableDoc, $mixedDoc);
    }
}
