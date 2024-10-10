<?php

namespace Tests\Unit;

use Laravel\Scout\Searchable;
use Matchish\ScoutElasticSearch\MixedSearch;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MixedTest extends TestCase
{
    public function test_search_signature()
    {
        $searchable = new ReflectionClass(Searchable::class);
        $searchableParameters = $searchable->getMethod('search')->getParameters();
        $searchableDoc = $this->stripStaticFromReturnType($searchable->getMethod('search')->getDocComment());

        $mixed = new ReflectionClass(MixedSearch::class);
        $mixedParameters = $mixed->getMethod('search')->getParameters();
        $mixedDoc = $mixed->getMethod('search')->getDocComment();

        $this->assertEquals($searchableParameters, $mixedParameters);
        $this->assertEquals($searchableDoc, $mixedDoc);
    }

    /**
     * Helper method to remove "static" from the @return line in the doc comment.
     */
    private function stripStaticFromReturnType($doc)
    {
        return preg_replace('/@return\s+\\\\Laravel\\\\Scout\\\\Builder<static>/', '@return \Laravel\Scout\Builder', $doc);
    }
}
