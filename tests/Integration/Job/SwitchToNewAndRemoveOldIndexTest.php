<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Product;
use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Jobs\RefreshIndex;
use Matchish\ScoutElasticSearch\Jobs\SwitchToNewAndRemoveOldIndex;
use Tests\TestCase;

final class SwitchToNewAndRemoveOldIndexTest extends TestCase
{

    public function testSwitchToNewAndRemoveOldIndex(): void
    {
        $elasticsearch = $this->app->make(Client::class);
        $elasticsearch->indices()->create([
            'index' => 'products_new',
            'body' => ['aliases' => ['products' => ['is_write_index' => true]]]
        ]);
        $elasticsearch->indices()->create([
            'index' => 'products_old',
            'body' => ['aliases' => ['products' => new \stdClass()]]
        ]);

        $job = new SwitchToNewAndRemoveOldIndex(new Index('products', 'products_new'));
        $job->handle($elasticsearch);

        $newIndexExist = $elasticsearch->indices()->exists(['index' => 'products_new']);
        $oldIndexExist = $elasticsearch->indices()->exists(['index' => 'products_old']);
        $alias = $elasticsearch->indices()->getAlias(['index' => 'products_new']);

        $this->assertTrue($newIndexExist);
        $this->assertFalse($oldIndexExist);
        $this->assertEquals(['products_new' => [
            'aliases' => ['products' => []]
        ]], $alias);

    }

}
