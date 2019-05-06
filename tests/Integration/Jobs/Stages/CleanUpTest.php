<?php

namespace Tests\Integration\Jobs\Stages;

use stdClass;
use App\Product;
use Tests\IntegrationTestCase;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUp;

class CleanUpTest extends IntegrationTestCase
{
    public function test_remove_write_index()
    {
        $this->elasticsearch->indices()->create([
            'index' => 'products_old',
            'body' => ['aliases' => ['products' => new stdClass()]],
        ]);
        $this->elasticsearch->indices()->create([
            'index' => 'products_new',
            'body' => ['aliases' => ['products' => ['is_write_index' => true], 'products1' => ['is_write_index' => true]]],
        ]);
        $this->elasticsearch->indices()->create([
            'index' => 'products_third',
            'body' => ['aliases' => ['products' => ['is_write_index' => false]]],
        ]);

        $stage = new CleanUp(new Product());
        $stage->handle($this->elasticsearch);
        $writeIndexExist = $this->elasticsearch->indices()->exists(['index' => 'products_new']);
        $readIndexExist = $this->elasticsearch->indices()->exists(['index' => 'products_old']);

        $this->assertFalse($writeIndexExist);
        $this->assertTrue($readIndexExist);
    }
}
