<?php
declare(strict_types=1);

namespace Tests\Integration\Pipelines\Stages;

use App\Product;
use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Pipelines\Stages\SwitchToNewAndRemoveOldIndex;
use Tests\IntegrationTestCase;

final class SwitchToNewAndRemoveOldIndexTest extends IntegrationTestCase
{

    public function test_switch_to_new_and_remove_old_index(): void
    {
        $this->elasticsearch->indices()->create([
            'index' => 'products_new',
            'body' => ['aliases' => ['products' => ['is_write_index' => true]]]
        ]);
        $this->elasticsearch->indices()->create([
            'index' => 'products_old',
            'body' => ['aliases' => ['products' => new \stdClass()]]
        ]);

        $stage = new SwitchToNewAndRemoveOldIndex($this->elasticsearch);
        $stage([new Index('products_new'), new Product()]);

        $newIndexExist = $this->elasticsearch->indices()->exists(['index' => 'products_new']);
        $oldIndexExist = $this->elasticsearch->indices()->exists(['index' => 'products_old']);
        $alias = $this->elasticsearch->indices()->getAlias(['index' => 'products_new']);

        $this->assertTrue($newIndexExist);
        $this->assertFalse($oldIndexExist);
        $this->assertEquals(['products_new' => [
            'aliases' => ['products' => []]
        ]], $alias);

    }

}
