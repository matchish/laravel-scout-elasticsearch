<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Matchish\ScoutElasticSearch\Console\Commands\FlushCommand;
use Matchish\ScoutElasticSearch\Console\Commands\ImportCommand;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Matchish\ScoutElasticSearch\Searchable\ImportSourceFactory;

final class ScoutElasticSearchServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'scout');

        $this->app->make(EngineManager::class)->extend(ElasticSearchEngine::class, function () {
            $elasticsearch = app(Client::class);

            return new ElasticSearchEngine($elasticsearch);
        });
        $this->registerCommands();
    }

    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $this->app->register(ScoutServiceProvider::class);
        $this->app->bind(ImportSourceFactory::class, DefaultImportSourceFactory::class);
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
