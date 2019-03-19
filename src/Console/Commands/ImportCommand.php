<?php
declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Console\Commands;

use Illuminate\Console\Command;
use Matchish\ScoutElasticSearch\Jobs\Import;
use Matchish\ScoutElasticSearch\Searchable\SearchableListFactory;

final class ImportCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'scout:import {searchable? : The name of the searchable}';
    /**
     * {@inheritdoc}
     */
    protected $description = 'Create new index and import all searchable into the one';

    /**
     * {@inheritdoc}
     */
    public function handle(SearchableListFactory $factory): void
    {
        $command = $this;
        $searchables = (array)$command->argument('searchable');
        $factory->make()->each(function ($searchable){
            $job = new Import($searchable);
            if (config('scout.queue')) {
                dispatch($job)->allOnQueue((new $searchable)->syncWithSearchUsingQueue())
                    ->allOnConnection(config((new $searchable)->syncWithSearchUsing()));
                $this->output->success('All [' . $searchable . '] records have been dispatched to import job.');
            } else {
                dispatch_now($job);
                $this->output->success('All [' . $searchable . '] records have been searchable.');
            }
        });

    }

}
