<?php

declare(strict_types=1);

namespace Tests;
use Elasticsearch\Client;
use Illuminate\Support\Facades\Artisan;

use Laravel\Scout\ScoutServiceProvider;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;
use Matchish\ScoutElasticSearch\ScoutElasticSearchServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->app->setBasePath(__DIR__.'/laravel');

        $this->withFactories(database_path('factories'));

        /** @var Client $elasticsearch */
        $elasticsearch = $this->app->make(Client::class);

        $elasticsearch->indices()->delete(['index' => '_all']);

        Artisan::call('migrate:fresh', ['--database' => 'testbench']);
    }

        /**
         * Define environment setup.
         *
         * @param  \Illuminate\Foundation\Application $app
         * @return void
         */
        protected function getEnvironmentSetUp($app)
        {
            $app['config']->set('scout.driver', ElasticSearchEngine::class);
            $app['config']->set('scout.queue', false);
            // Setup default database to use sqlite :memory:
            $app['config']->set('database.default', 'testbench');
            $app['config']->set('database.connections.testbench', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }

    protected function getPackageProviders($app)
    {
        return [
            ScoutServiceProvider::class,
            ScoutElasticSearchServiceProvider::class,
        ];
    }
}
