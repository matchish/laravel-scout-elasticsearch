<?php

namespace Tests;

use App\Product;
use Elastic\Elasticsearch\Client;

/**
 * Class IntegrationTestCase.
 */
class IntegrationTestCase extends TestCase
{
    /**
     * @var Client
     */
    protected $elasticsearch;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->elasticsearch = $this->app->make(Client::class);

        $this->elasticsearch->indices()->delete(['index' => '_all']);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('elasticsearch', require(__DIR__.'/../config/elasticsearch.php'));
        if (class_exists(\Junges\TrackableJobs\Providers\TrackableJobsServiceProvider::class)) {
            $app['config']->set('trackable-job', require(__DIR__.'/../vendor/mateusjunges/laravel-trackable-jobs/config/trackable-jobs.php'));
        }
        $app['config']->set('elasticsearch.indices.mappings.products', [
            'properties' => [
                'type' => [
                    'type' => 'keyword',
                ],
                'price' => [
                    'type' => 'integer',
                ],
            ],
        ]);
        Product::preventAccessingMissingAttributes();
    }
}
