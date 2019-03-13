<?php
declare(strict_types=1);

namespace Tests\Feature;

use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\CreateWriteIndex;
use Tests\IntegrationTestCase;

final class CreateWriteIndexTest extends IntegrationTestCase
{
    /**
     * @testdox Create index and add write alias to it
     */
    public function testCreateWriteIndex(): void
    {
        $job = new CreateWriteIndex(new Index('products'));
        $elasticsearch = $this->app->make(Client::class);
        $job->handle($elasticsearch);
        $response = $elasticsearch->indices()->getAliases(['index' => '*', 'name' => 'products']);
        $this->assertTrue($this->containsWriteIndex($response, 'products'));
    }

    private function containsWriteIndex($response, $requiredAlias)
    {
        foreach ($response as $index) {
            foreach ($index['aliases'] as $alias => $data) {
                if ($alias == $requiredAlias && $data['is_write_index']) {
                    return true;
                }
            }
        }
        return false;
    }

}
