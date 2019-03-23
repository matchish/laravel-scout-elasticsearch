<?php
namespace Tests\Integration\Pipelines\Stages;

use App\Product;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Pipelines\Stages\CleanUp;
use Tests\IntegrationTestCase;

class CleanUpTest extends IntegrationTestCase
{

    public function test_remove_write_index()
    {
        $this->elasticsearch->indices()->create([
            'index' => 'products_old',
            'body' => ['aliases' => ['products' => new \stdClass()]]
        ]);
        $this->elasticsearch->indices()->create([
            'index' => 'products_new',
            'body' => ['aliases' => ['products' => ['is_write_index' => true], 'products1' => ['is_write_index' => true]]]
        ]);
        $this->elasticsearch->indices()->create([
            'index' => 'products_third',
            'body' => ['aliases' => ['products' => ['is_write_index' => false]]]
        ]);

        $stage = new CleanUp($this->elasticsearch);
        $stage([new Index(new Product()), new Product()]);
        $writeIndexExist = $this->elasticsearch->indices()->exists(['index' => 'products_new']);
        $readIndexExist = $this->elasticsearch->indices()->exists(['index' => 'products_old']);

        $this->assertFalse($writeIndexExist);
        $this->assertTrue($readIndexExist);
    }

    public function test_return_same_payload()
    {
        $stage = new CleanUp($this->elasticsearch);
        $payload = [new Index(new Product()), new Product()];
        $nextPayload = $stage($payload);
        $this->assertEquals(2, count($nextPayload));
        $this->assertSame($payload[0], $nextPayload[0]);
        $this->assertSame($payload[1], $nextPayload[1]);
    }
}
