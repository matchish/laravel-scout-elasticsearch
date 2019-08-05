<?php

namespace Tests;

use Elasticsearch\Client;

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
}
