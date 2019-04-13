<?php

namespace Matchish\ScoutElasticSearch;

use Tests\TestCase;

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

        $this->assertFileExists(config_path('elasticsearch.php'));

    }
}
