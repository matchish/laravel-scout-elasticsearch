<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch;

use Elasticsearch\Client;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Matchish\ScoutElasticSearch\Console\Commands\FlushCommand;
use Matchish\ScoutElasticSearch\Console\Commands\ImportCommand;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;

final class ScoutElasticSearchServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'scout');

        resolve(EngineManager::class)->extend(ElasticSearchEngine::class, function () {
            $elasticsearch = resolve(Client::class);

            return new ElasticSearchEngine($elasticsearch);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $this->app->register(ScoutServiceProvider::class);

        $this->registerCommands();
    }

    /**
     * Register artisan commands.
     *
     * @return void
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
                FlushCommand::class,
            ]);
        }
    }
}
