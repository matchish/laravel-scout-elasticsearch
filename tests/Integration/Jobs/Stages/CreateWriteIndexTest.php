<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs\Stages;

use App\Product;
use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\Stages\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Tests\IntegrationTestCase;

final class CreateWriteIndexTest extends IntegrationTestCase
{
    public function test_create_write_index(): void
    {
        $elasticsearch = $this->app->make(Client::class);
        $stage = new CreateWriteIndex(DefaultImportSourceFactory::from(Product::class), Index::fromSource(DefaultImportSourceFactory::from(Product::class)));
        $stage->handle($elasticsearch);
        $response = $elasticsearch->indices()->getAlias(['index' => '*', 'name' => 'products']);
        $this->assertTrue($this->containsWriteIndex($response, 'products'));
    }

    private function containsWriteIndex($response, $requiredAlias)
    {
        foreach ($response as $index) {
            foreach ($index['aliases'] as $alias => $data) {
                if ($alias == $requiredAlias && array_key_exists('is_write_index', $data) && $data['is_write_index']) {
                    return true;
                }
            }
        }

        return false;
    }
}
