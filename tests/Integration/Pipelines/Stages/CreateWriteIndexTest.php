<?php
declare(strict_types=1);

namespace Tests\Integration\Pipelines\Stages;

use App\Product;
use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Pipelines\Stages\CreateWriteIndex;
use Tests\IntegrationTestCase;

final class CreateWriteIndexTest extends IntegrationTestCase
{
    /**
     * @testdox Create index and add write alias to it
     */
    public function testCreateWriteIndex(): void
    {
        $elasticsearch = $this->app->make(Client::class);
        $stage = new CreateWriteIndex($elasticsearch);
        $stage([Index::fromSearchable(new Product()), new Product()]);
        $response = $elasticsearch->indices()->getAliases(['index' => '*', 'name' => 'products']);
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
