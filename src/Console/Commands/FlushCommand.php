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
    protected $signature = 'scout:flush {searchable? : The name of the searchable}';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Flush the index of the the given searchable';

    /**
     * {@inheritdoc}
     */
    public function handle(SearchableListFactory $factory): void
    {
        $command = $this;
        $searchables = (array) $command->argument('searchable');
        $factory->make()->each(function ($searchable) {
            $searchable::removeAllFromSearch();
            $this->output->success('All ['.$searchable.'] records have been flushed.');
        });
    }
}
