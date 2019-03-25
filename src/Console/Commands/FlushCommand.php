<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Console\Commands;

use Illuminate\Console\Command;
use Matchish\ScoutElasticSearch\Searchable\SearchableListFactory;

final class FlushCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'scout:flush {searchable?* : The name of the searchable}';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Flush the index of the the given searchable';

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        $command = $this;
        $searchableList = collect($command->argument('searchable'))->whenEmpty(function () {
            $factory = new SearchableListFactory(app()->getNamespace(), app()->path());
            return $factory->make();
        });
        $searchableList->each(function ($searchable){
            $searchable::removeAllFromSearch();
            $this->output->success('All ['.$searchable.'] records have been flushed.');
        });
    }
}
