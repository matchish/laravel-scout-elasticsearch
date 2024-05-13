<?php

declare(strict_types=1);

namespace Tests;

use Laravel\Scout\ScoutServiceProvider;
use Matchish\ScoutElasticSearch\ElasticSearchServiceProvider;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;
use Matchish\ScoutElasticSearch\ScoutElasticSearchServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->app->setBasePath(__DIR__.'/laravel');

        $this->withFactories(database_path('factories'));

        if (class_exists(\Junges\TrackableJobs\Providers\TrackableJobsServiceProvider::class)) {
            \Artisan::call('vendor:publish', [
                '--tag' => 'trackable-jobs-config'
            ]);
        }

        \Artisan::call('migrate:fresh', ['--database' => 'mysql']);
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('scout.driver', ElasticSearchEngine::class);
        $app['config']->set('scout.chunk.searchable', 3);
        $app['config']->set('scout.chunk.handlers', 1);
        $app['config']->set('scout.queue', false);
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'mysql');
    }

    protected function getPackageProviders($app)
    {
        /** @var array<class-string> $providers */
        $providers = [
            ScoutServiceProvider::class,
            ScoutElasticSearchServiceProvider::class,
            ElasticSearchServiceProvider::class,
        ];

        if (class_exists(\Junges\TrackableJobs\Providers\TrackableJobsServiceProvider::class)) {
            $providers[] = \Junges\TrackableJobs\Providers\TrackableJobsServiceProvider::class;
        }

        return $providers;
    }
}
