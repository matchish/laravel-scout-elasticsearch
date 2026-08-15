<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Console\Commands;

use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\Jobs\ImportBatches;
use Matchish\ScoutElasticSearch\Searchable\ImportSourceFactory;
use Matchish\ScoutElasticSearch\Searchable\SearchableListFactory;

final class ImportStatusCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'scout:import:status {searchable?* : The name of the searchable}';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Show the progress of parallel imports';

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        $rows = $this->searchableList((array) $this->argument('searchable'))
            ->map(function (string $searchable) {
                return $this->row($searchable);
            })
            ->all();

        if ($rows === []) {
            $this->line('No searchable models found.');

            return self::SUCCESS;
        }

        $this->table(['Index', 'Status', 'Progress', 'Ranges', 'Failed', 'Started'], $rows);

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function row(string $searchable): array
    {
        $sourceFactory = app(ImportSourceFactory::class);
        $index = $sourceFactory::from($searchable)->searchableAs();
        $batch = ImportBatches::latest($index);

        if ($batch === null) {
            return [$index, '<comment>no import found</comment>', '-', '-', '-', '-'];
        }

        $finished = $batch->totalJobs - $batch->pendingJobs;

        return [
            $index,
            $this->status($batch),
            $batch->progress().'%',
            $finished.'/'.$batch->totalJobs,
            $batch->failedJobs > 0 ? '<fg=red>'.$batch->failedJobs.'</>' : '0',
            $batch->createdAt->diffForHumans(),
        ];
    }

    private function status(Batch $batch): string
    {
        if ($batch->cancelled()) {
            return '<comment>cancelled</comment>';
        }

        if ($batch->failedJobs > 0) {
            return '<fg=red>failed</>';
        }

        if ($batch->finished()) {
            return '<info>finished</info>';
        }

        return '<info>running</info>';
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
}
