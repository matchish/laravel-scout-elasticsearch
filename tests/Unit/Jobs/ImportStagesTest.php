<?php

namespace Tests\Unit\Jobs;

use App\Product;
use Matchish\ScoutElasticSearch\Jobs\ImportStages;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Jobs\Stages\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\PullFromSource;
use Matchish\ScoutElasticSearch\Jobs\Stages\RefreshIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\SwitchToNewAndRemoveOldIndex;
use Tests\TestCase;

class ImportStagesTest extends TestCase
{
    public function test_no_stages_if_no_searchables()
    {
        $stages = ImportStages::fromSearchable(new Product());
        $this->assertEquals(0, $stages->count());
    }

    public function test_stages()
    {
        factory(Product::class, 10)->create();
        $stages = ImportStages::fromSearchable(new Product());
        $this->assertEquals(8, $stages->count());
        $this->assertInstanceOf(CleanUp::class, $stages->get(0));
        $this->assertInstanceOf(CreateWriteIndex::class, $stages->get(1));
        $this->assertInstanceOf(PullFromSource::class, $stages->get(2));
        $this->assertInstanceOf(PullFromSource::class, $stages->get(3));
        $this->assertInstanceOf(PullFromSource::class, $stages->get(4));
        $this->assertInstanceOf(PullFromSource::class, $stages->get(5));
        $this->assertInstanceOf(RefreshIndex::class, $stages->get(6));
        $this->assertInstanceOf(SwitchToNewAndRemoveOldIndex::class, $stages->get(7));
    }
}
