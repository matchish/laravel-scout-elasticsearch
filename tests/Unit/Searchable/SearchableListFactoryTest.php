<?php

namespace Tests\Unit\Searchable;

use Matchish\ScoutElasticSearch\Searchable\SearchableListFactory;
use Tests\TestCase;

class SearchableListFactoryTest extends TestCase
{
    public function test_only_load_seachable_classes()
    {
        $this->assertFileExists(app()->path() . '/Providers/TelescopeServiceProvider.php');

        // This should NOT throw "Error: Class 'Laravel\Telescope\TelescopeApplicationServiceProvider' not found"
        $factory = new SearchableListFactory(app()->getNamespace(), app()->path());

        $searchable = $factory->make();

        // There are 4 searchable models: Book, BookWithCustomKey, Product and Ticket
        $this->assertCount(4, $searchable);
    }
}
