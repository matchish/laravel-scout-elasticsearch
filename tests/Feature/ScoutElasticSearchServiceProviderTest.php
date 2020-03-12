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

        // assert config is loaded from vendor before publishing
        $distConfig = config('elasticsearch', false);
        $this->assertNotFalse($distConfig);
        $this->assertArrayHasKey('indices', $distConfig);

        \Artisan::call('vendor:publish', [
            '--tag' => 'config',
        ]);

        // assert config is the same as before publishing
        $publishedConfig = config('elasticsearch', false);

        $this->assertEquals($distConfig, $publishedConfig);

        $this->assertFileExists(config_path('elasticsearch.php'));
    }

    public function test_provides()
    {
        $provider = new ElasticSearchServiceProvider($this->app);
        $this->assertEquals([Client::class], $provider->provides());
    }
}
