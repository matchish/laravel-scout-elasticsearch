<?php

namespace Matchish\ScoutElasticSearch;

use Elasticsearch\Client;
use Tests\TestCase;

class ScoutElasticSearchServiceProviderTest extends TestCase
{
    public function test_config_is_merged_from_the_package()
    {
        $provider = new ElasticSearchServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertNotFalse(config('elasticsearch', false));
    }

    public function test_provides()
    {
        $provider = new ElasticSearchServiceProvider($this->app);
        $this->assertEquals([Client::class], $provider->provides());
    }
}
