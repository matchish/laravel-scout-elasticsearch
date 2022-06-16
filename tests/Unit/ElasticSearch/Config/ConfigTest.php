<?php

namespace Tests\Unit\ElasticSearch\Config;

use Illuminate\Support\Facades\Config;
use Matchish\ScoutElasticSearch\ElasticSearch\Config\Config as ScoutConfig;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_parse_host(): void
    {
        Config::set('elasticsearch.host', 'http://localhost:9200');

        $config = new ScoutConfig();
        $this->assertEquals(['http://localhost:9200'], $config::hosts());
    }

    public function test_parse_multihost(): void
    {
        Config::set('elasticsearch.host', 'http://localhost:9200,http://localhost:9201');

        $config = new ScoutConfig();
        $this->assertEquals(['http://localhost:9200', 'http://localhost:9201'], $config::hosts());
    }

    public function test_parse_username_password(): void
    {
        Config::set('elasticsearch.host', 'http://localhost:9200,http://localhost:9201');
        Config::set('elasticsearch.user', 'elastic');
        Config::set('elasticsearch.password', 'pass');

        $config = new ScoutConfig();
        $this->assertEquals('elastic', $config::user());
        $this->assertEquals('pass', $config::password());
    }

    public function test_parse_elastic_cloud_id(): void
    {
        Config::set('elasticsearch.host', 'http://localhost:9200,http://localhost:9201');
        Config::set('elasticsearch.cloud_id', 'cloud-id');
        Config::set('elasticsearch.api_key', '123456');

        $config = new ScoutConfig();
        $this->assertEquals('cloud-id', $config::elasticCloudId());
        $this->assertEquals('123456', $config::apiKey());
    }
}
