<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 13.03.19
 * Time: 15:18
 */

namespace Tests;


use Elasticsearch\Client;

/**
 * Class IntegrationTestCase
 * @package Tests
 */
class IntegrationTestCase extends TestCase
{
    /**
     * @var Client
     */
    protected $elasticsearch;

    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->elasticsearch = $this->app->make(Client::class);

        $this->elasticsearch->indices()->delete(['index' => '_all']);
    }
}