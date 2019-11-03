<?php

namespace Matchish\ScoutElasticSearch;

use Elasticsearch\Client;
use Tests\TestCase;

class ScoutElasticSearchServiceProviderTest extends TestCase
{
    public function test_config_publishing()
    {
        \File::delete(config_path('elasticsearch.php'));
        $provider = new ElasticSearchServiceProvider($this->app);
        $provider->boot();

        \Artisan::call('vendor:publish', [
            '--tag' => 'config',
        ]);

        $this->assertFileExists(config_path('elasticsearch.php'));
    }

    public function test_provides()
    {
        $provider = new ElasticSearchServiceProvider($this->app);
        $this->assertEquals([Client::class], $provider->provides());
    }
}
