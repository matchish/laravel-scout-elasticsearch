<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\ElasticSearch\Config\Config;
use Matchish\ScoutElasticSearch\Jobs\Import;
use Matchish\ScoutElasticSearch\Jobs\QueueableJob;
use Matchish\ScoutElasticSearch\Searchable\ImportSourceFactory;
use Matchish\ScoutElasticSearch\Searchable\SearchableListFactory;

final class ImportCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'scout:import {searchable?* : The name of the searchable} {--P|parallel : Index items in parallel}';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Create new index and import all searchable into the one';

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        $parallel = false;

        if ($this->option('parallel')) {
            $parallel = true;
        }

        $this->searchableList((array) $this->argument('searchable'))
        ->each(function (string $searchable) use ($parallel) {
            $this->import($searchable, $parallel);
        });
    }

    /**
     * @param  array<string>  $argument
     * @return Collection<int, string>
     */
    private function searchableList(array $argument): Collection
    {
        return collect($argument)->whenEmpty(function () {
            $factory = new SearchableListFactory(app()->getNamespace(), app()->path());

            return $factory->make();
        });
    }

    /**
     * @param  string  $searchable
     * @param  bool  $parallel
     * @return void
     */
    private function import(string $searchable, bool $parallel): void
    {
        $sourceFactory = app(ImportSourceFactory::class);
        $source = $sourceFactory::from($searchable);
        $job = new Import($source);
        /** @var int|null $queueTimeout */
        $queueTimeout = Config::queueTimeout();
        if ($queueTimeout !== null) {
            $job->timeout = (int) $queueTimeout;
        }
        $job->parallel = $parallel;

        if (config('scout.queue')) {
            $job = (new QueueableJob())->chain([$job]);
            /** @var int|null $queueTimeout */
            $queueTimeout = Config::queueTimeout();
            if ($queueTimeout !== null) {
                $job->timeout = (int) $queueTimeout;
            }
        }
        $bar = (new ProgressBarFactory($this->output))->create();
        $job->withProgressReport($bar);

        $startMessage = trans('scout::import.start', ['searchable' => "<comment>$searchable</comment>"]);
        $this->line($startMessage);

        /* @var ImportSource $source */
        dispatch($job)->allOnQueue($source->syncWithSearchUsingQueue())
            ->allOnConnection($source->syncWithSearchUsing());

        $doneMessage = trans(config('scout.queue') ? 'scout::import.done.queue' : 'scout::import.done', [
            'searchable' => $searchable,
        ]);
        $this->output->success($doneMessage);
    }
}
