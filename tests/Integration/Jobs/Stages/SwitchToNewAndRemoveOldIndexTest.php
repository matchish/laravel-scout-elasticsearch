<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs\Stages;

use stdClass;
use App\Product;
use Tests\IntegrationTestCase;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\Stages\SwitchToNewAndRemoveOldIndex;

final class SwitchToNewAndRemoveOldIndexTest extends IntegrationTestCase
{
    public function test_switch_to_new_and_remove_old_index(): void
    {
        $this->elasticsearch->indices()->create([
            'index' => 'products_new',
            'body' => ['aliases' => ['products' => ['is_write_index' => true]]],
        ]);
        $this->elasticsearch->indices()->create([
            'index' => 'products_old',
            'body' => ['aliases' => ['products' => new stdClass()]],
        ]);

        $stage = new SwitchToNewAndRemoveOldIndex(new Product(), new Index('products_new'));
        $stage->handle($this->elasticsearch);

        $newIndexExist = $this->elasticsearch->indices()->exists(['index' => 'products_new']);
        $oldIndexExist = $this->elasticsearch->indices()->exists(['index' => 'products_old']);
        $alias = $this->elasticsearch->indices()->getAlias(['index' => 'products_new']);

        $this->assertTrue($newIndexExist);
        $this->assertFalse($oldIndexExist);
        $this->assertEquals(['products_new' => [
            'aliases' => ['products' => []],
        ]], $alias);
    }
}
