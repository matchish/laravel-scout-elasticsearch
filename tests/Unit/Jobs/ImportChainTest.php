<?php
declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Product;
use Matchish\ScoutElasticSearch\Jobs\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Jobs\ImportChain;
use Matchish\ScoutElasticSearch\Jobs\MakeAllSearchable;
use Matchish\ScoutElasticSearch\Jobs\RefreshIndex;
use Matchish\ScoutElasticSearch\Jobs\SwitchToNewAndRemoveOldIndex;
use Tests\TestCase;

class ImportChainTest extends \Orchestra\Testbench\TestCase
{
    public function testChainCreation(): void
    {
        $chain = ImportChain::from(Product::class);
        $this->assertEquals(4, count($chain));
        $this->assertInstanceOf(CreateWriteIndex::class, $chain[0]);
        $this->assertInstanceOf(MakeAllSearchable::class, $chain[1]);
        $this->assertInstanceOf(RefreshIndex::class, $chain[2]);
        $this->assertInstanceOf(SwitchToNewAndRemoveOldIndex::class, $chain[3]);
    }
}
