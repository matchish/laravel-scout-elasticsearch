<?php

namespace Matchish\ScoutElasticSearch;

use App\Product;
use Tests\TestCase;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;

class ScoutElasticSearchServiceProviderTest extends TestCase
{
    public function testConfigPublishing()
    {
        \File::delete(config_path('elasticsearch.php'));
        $provider = new ScoutElasticSearchServiceProvider($this->app);
        $provider->boot();

        \Artisan::call('vendor:publish', [
            '--tag' => 'config',
        ]);

        app('config')->set('elasticsearch', require config_path('elasticsearch.php'));

        $this->assertFileExists(config_path('elasticsearch.php'));

        $index = Index::fromSearchable(new Product());
        $this->assertEquals([
            'mappings' => [
                '_doc' => [
                    'properties' => [
                        'created_at' => [
                            'type' => 'date',
                        ],
                        'updated_at' => [
                            'type' => 'date',
                        ],
                        'deleted_at' => [
                            'type' => 'date',
                        ],
                    ],
                ], ],
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ], $index->config());
    }
}
