<?php

namespace Tests\Unit\Searchable;

use App\Ticket;
use App\Traits\Searchable;
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

        // There are 5 searchable models: Book, BookWithCustomKey, Product, Ticket and Post
        $this->assertCount(5, $searchable);
    }

    public function test_find_searchable_trait_within_trait()
    {
        $this->assertContains(Searchable::class, class_uses(Ticket::class, true));

        // This should NOT throw "Error: Class 'Laravel\Telescope\TelescopeApplicationServiceProvider' not found"
        $factory = new SearchableListFactory(app()->getNamespace(), app()->path());

        $searchable = $factory->make();

        // Model Ticket has trait that implements the Searchable trait.
        $this->assertContains(Ticket::class, $searchable);
    }
}
