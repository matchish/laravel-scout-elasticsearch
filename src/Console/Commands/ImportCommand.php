<?php
declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Console\Commands;

use Illuminate\Console\Command;
use Matchish\ScoutElasticSearch\Jobs\ImportChain;
use Matchish\ScoutElasticSearch\Jobs\PendingChain;
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
        $factory->make()->each(function ($searchable) {

            $chain = ImportChain::from($searchable);

            if (config('scout.queue')) {
                (new PendingChain($chain->all()))->dispatch()->allOnQueue((new $searchable)->syncWithSearchUsingQueue())
                    ->allOnConnection(config((new $searchable)->syncWithSearchUsing()));
                $this->output->success('All [' . $searchable . '] records have been dispatched to import job.');
            } else {
                foreach ($chain as $job) {
                    dispatch_now($job);
                }
                $this->output->success('All [' . $searchable . '] records have been searchable.');
            }
        });

    }

}
