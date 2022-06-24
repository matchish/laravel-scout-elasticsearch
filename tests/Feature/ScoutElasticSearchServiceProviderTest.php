<?php

namespace Matchish\ScoutElasticSearch;

use Elastic\Elasticsearch\Client;
use Elastic\Transport\Exception\NoNodeAvailableException;
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

    public function test_config_with_username()
    {
        $this->app['config']->set('elasticsearch.host', 'http://localhost:9200');
        $this->app['config']->set('elasticsearch.user', 'elastic');
        $this->app['config']->set('elasticsearch.password', 'pass');
        $provider = new ElasticSearchServiceProvider($this->app);
        $this->assertEquals([Client::class], $provider->provides());
        /** @var Client $client */
        $client = $this->app[Client::class];
        try {
            $client->info();
        } catch (NoNodeAvailableException $e) {
            $this->assertTrue(true);
        }
        $this->assertEquals('elastic:pass', $client->getTransport()->getLastRequest()->getUri()->getUserInfo());
    }

    public function test_config_with_cloud_id()
    {
        $this->app['config']->set('elasticsearch.cloud_id', 'Test:ZXUtY2VudHJhbC0xLmF3cy5jbG91ZC5lcy5pbyQ0ZGU0NmNlZDhkOGQ0NTk2OTZlNTQ0ZmU1ZjMyYjk5OSRlY2I0YTJlZmY0OTA0ZDliOTE5NzMzMmQwOWNjOTY5Ng==');
        $this->app['config']->set('elasticsearch.api_key', '123456');
        $this->app['config']->set('elasticsearch.user', null);
        $provider = new ElasticSearchServiceProvider($this->app);
        $this->assertEquals([Client::class], $provider->provides());
        /** @var Client $client */
        $client = $this->app[Client::class];
        $this->assertEquals('ApiKey 123456', $client->getTransport()->getHeaders()['Authorization']);
        $this->assertEquals('4de46ced8d8d459696e544fe5f32b999.eu-central-1.aws.cloud.es.io', $client->getTransport()->getNodePool()->nextNode()->getUri()->getHost());
    }
}
