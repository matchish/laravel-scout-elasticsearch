<?php

namespace Tests\Unit\Searchable;

use App\Ticket;
use Matchish\ScoutElasticSearch\Searchable\SearchableListFactory;
use Tests\TestCase;

class SearchableListFactoryTest extends TestCase
{
    public function test_only_load_seachable_classes()
    {
        $this->assertFileExists(app()->path().'/Providers/TelescopeServiceProvider.php');

        // This should NOT throw "Error: Class 'Laravel\Telescope\TelescopeApplicationServiceProvider' not found"
        $factory = new SearchableListFactory(app()->getNamespace(), app()->path());

        $searchable = $factory->make();

        // There are 4 searchable models: Book, BookWithCustomKey, Product and Ticket
        $this->assertCount(4, $searchable);
    }

    public function test_find_searchable_trait_within_trait()
    {
        $this->assertFileExists(app()->path().'/Traits/Searchable.php');

        // This should NOT throw "Error: Class 'Laravel\Telescope\TelescopeApplicationServiceProvider' not found"
        $factory = new SearchableListFactory(app()->getNamespace(), app()->path());

        $searchable = $factory->make();

        // Model Ticket has trait that implements the Searchable trait.
        $this->assertContains(Ticket::class, $searchable);
    }
}
