<?php

namespace Tests\Unit;

use Laravel\Scout\Searchable;
use Matchish\ScoutElasticSearch\MixedSearch;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MixedSearchTest extends TestCase
{
    public function test_search_signature()
    {
        $searchable = new ReflectionClass(Searchable::class);
        $searchableParameters = $searchable->getMethod('search')->getParameters();
        $searchableDoc = $searchable->getMethod('search')->getDocComment();
        $mixed = new ReflectionClass(MixedSearch::class);
        $mixedParameters = $mixed->getMethod('search')->getParameters();
        $mixedDoc = $mixed->getMethod('search')->getDocComment();

        $this->assertEquals($searchableParameters, $mixedParameters);
        $this->assertEquals($searchableDoc, $mixedDoc);
    }
}
