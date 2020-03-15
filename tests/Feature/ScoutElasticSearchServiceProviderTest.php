<?php

namespace Matchish\ScoutElasticSearch;

use Elasticsearch\Client;
use Tests\TestCase;

class ScoutElasticSearchServiceProviderTest extends TestCase
{
    public function test_config_is_merged_from_the_package()
    {
        $distConfig = require __DIR__.'/../../config/elasticsearch.php';

        $this->assertSame($distConfig, config('elasticsearch'));
    }

    public function test_config_publishing()
    {
        $provider = new ElasticSearchServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->artisan('vendor:publish', [
            '--tag' => 'config',
        ])->assertExitCode(0);

        $this->assertFileExists(config_path('elasticsearch.php'));

        \File::delete(config_path('elasticsearch.php'));
    }

    public function test_provides()
    {
        $provider = new ElasticSearchServiceProvider($this->app);
        $this->assertEquals([Client::class], $provider->provides());
    }
}
