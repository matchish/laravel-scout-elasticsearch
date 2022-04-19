<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs\Stages;

use App\Product;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\Stages\SwitchToNewAndRemoveOldIndex;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use stdClass;
use Tests\IntegrationTestCase;

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

        $stage = new SwitchToNewAndRemoveOldIndex(DefaultImportSourceFactory::from(Product::class), new Index('products_new'));
        $stage->handle($this->elasticsearch);

        $newIndexExist = $this->elasticsearch->indices()->exists(['index' => 'products_new'])->asBool();
        $oldIndexExist = $this->elasticsearch->indices()->exists(['index' => 'products_old'])->asBool();
        $alias = $this->elasticsearch->indices()->getAlias(['index' => 'products_new'])->asArray();

        $this->assertTrue($newIndexExist);
        $this->assertFalse($oldIndexExist);
        $this->assertEquals(['products_new' => [
            'aliases' => ['products' => []],
        ]], $alias);
    }
}
