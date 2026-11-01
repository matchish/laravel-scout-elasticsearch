<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Console\Commands;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\Jobs\ImportBatches;
use Matchish\ScoutElasticSearch\Jobs\ImportState;
use Matchish\ScoutElasticSearch\Jobs\Stages\WaitForPublish;
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
     * Why imports failed, printed below the table.
     *
     * @var array<int, string>
     */
    private $reasons = [];

    /**
     * {@inheritdoc}
     */
    public function handle(Client $elasticsearch): int
    {
        $rows = $this->searchableList((array) $this->argument('searchable'))
            ->map(function (string $searchable) use ($elasticsearch) {
                return $this->row($searchable, $elasticsearch);
            })
            ->all();

        if ($rows === []) {
            $this->line('No searchable models found.');

            return self::SUCCESS;
        }

        $this->table(['Index', 'Status', 'Progress', 'Ranges', 'Failed', 'Started'], $rows);
        foreach ($this->reasons as $reason) {
            $this->line($reason);
        }

        return self::SUCCESS;
    }

    /**
     * The import's own records decide its status: the ranges recorded in
     * its state, a recorded failure, and the search alias. The batch
     * counts job deliveries, and any queue may deliver a job twice, so it
     * only says whether the import was cancelled and how many jobs failed.
     *
     * @return array<int, string>
     */
    private function row(string $searchable, Client $elasticsearch): array
    {
        $sourceFactory = app(ImportSourceFactory::class);
        $index = $sourceFactory::from($searchable)->searchableAs();
        $batch = ImportBatches::latest($index);

        if ($batch === null) {
            return [$index, '<comment>no import found</comment>', '-', '-', '-', '-'];
        }

        $failed = $batch->failedJobs > 0 ? '<fg=red>'.$batch->failedJobs.'</>' : '0';
        $started = $batch->createdAt->diffForHumans();
        $import = ImportBatches::import($batch);
        if ($import === null) {
            return [$index, '<comment>unknown</comment>', '-', '-', $failed, $started];
        }

        $planned = $import['ranges'];
        $state = ImportState::forImport($import['index'], $planned);
        // Records first, then whether the state exists. A removed state
        // is never created again, so when it still exists after the
        // reads, the records came from the live state.
        $recorded = $state->recorded($elasticsearch);
        $failure = $state->failure($elasticsearch);

        if (! $state->exists($elasticsearch)) {
            // The final step removes the state after the alias switch; a
            // newer import removes it together with this import's index.
            if (WaitForPublish::published($elasticsearch, $import['index'], $index)) {
                return [$index, '<info>finished</info>', '100%', $planned.'/'.$planned, $failed, $started];
            }

            return [$index, '<comment>cancelled</comment>', '-', '-', $failed, $started];
        }

        return [
            $index,
            $this->status($index, $batch, $failure, $recorded === $planned),
            $this->progress($recorded, $planned),
            ($recorded ?? '-').'/'.$planned,
            $failed,
            $started,
        ];
    }

    /**
     * The status of an import whose state still exists. A cancelled batch
     * goes before the recorded ranges: the final step stands down for a
     * cancelled batch, so that import never publishes.
     */
    private function status(string $index, Batch $batch, ?string $failure, bool $complete): string
    {
        if ($failure !== null) {
            $this->reasons[] = sprintf(
                '%s: the final step failed: %s. The search alias was not switched, so the old index still serves searches.',
                $index,
                $failure
            );

            return '<fg=red>failed</>';
        }

        if ($batch->cancelled()) {
            if ($batch->failedJobs === 0) {
                return '<comment>cancelled</comment>';
            }
            $this->reasons[] = $index.': '.ImportBatches::cancelledError($batch)->getMessage();

            return '<fg=red>failed</>';
        }

        return $complete ? '<info>switching</info>' : '<info>running</info>';
    }

    /**
     * Recorded ranges out of planned ones. It reaches 100% only when every
     * range is recorded.
     */
    private function progress(?int $recorded, int $planned): string
    {
        if ($recorded === null) {
            return '-';
        }

        return ($planned > 0 ? intdiv(100 * $recorded, $planned) : 100).'%';
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
