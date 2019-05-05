<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Console\Commands;

use Illuminate\Console\Command;
use Matchish\ScoutElasticSearch\Jobs\Import;
use Matchish\ScoutElasticSearch\Jobs\QueueableJob;
use Matchish\ScoutElasticSearch\Searchable\SearchableListFactory;

final class ImportCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'scout:import {searchable?* : The name of the searchable}';
    /**
     * {@inheritdoc}
     */
    protected $description = 'Create new index and import all searchable into the one';

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        $this->searchableList($this->argument('searchable'))
        ->each(function ($searchable) {
            $this->import($searchable);
        });
    }

    private function searchableList($argument)
    {
        return collect($argument)->whenEmpty(function () {
            $factory = new SearchableListFactory(app()->getNamespace(), app()->path());
            return $factory->make();
        });

    }

    private function import($searchable)
    {
        $job = new Import($searchable);

        if (config('scout.queue')) {
            $job = (new QueueableJob())->chain([$job]);
        }

        $bar = (new ProgressBarFactory($this->output))->create();
        $job->withProgressReport($bar);

        $startMessage = config('scout.queue') ? "Dispatching import job to the queue" : "Starting import $searchable";
        $this->output->success($startMessage);

        dispatch($job)->allOnQueue((new $searchable)->syncWithSearchUsingQueue())
            ->allOnConnection(config((new $searchable)->syncWithSearchUsing()));

        $doneMessage = config('scout.queue') ? "All $searchable will be availiable for search soon" : "All $searchable searchable now";
        $this->output->success($doneMessage);

    }
}
