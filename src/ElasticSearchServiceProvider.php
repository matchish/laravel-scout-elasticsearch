<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;
use Matchish\ScoutElasticSearch\ElasticSearch\Config\Config;
use Matchish\ScoutElasticSearch\ElasticSearch\EloquentHitsIteratorAggregate;
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;

final class ElasticSearchServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/elasticsearch.php', 'elasticsearch');

        $this->app->bind(Client::class, function () {
            $config = (new Config())->parse();
            $clientBuilder = ClientBuilder::create()
                ->setHosts($config->hosts())
                ->setSSLVerification((bool) $config->sslVerification());
            if ($user = $config->user()) {
                /** @var string $user */
                $clientBuilder->setBasicAuthentication($user, (string) $config->password());
            }

            if ($cloudId = $config->elasticCloudId()) {
                /** @var string $cloudId */
                $clientBuilder->setElasticCloudId($cloudId)
                    ->setApiKey((string) $config->apiKey());
            }

            return $clientBuilder->build();
        });

        $this->app->bind(
            HitsIteratorAggregate::class,
            EloquentHitsIteratorAggregate::class
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/elasticsearch.php' => config_path('elasticsearch.php'),
        ], 'config');
    }

    /**
     * {@inheritdoc}
     * @return array<string>
     */
    public function provides(): array
    {
        return [Client::class];
    }
}
