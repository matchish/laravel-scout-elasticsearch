<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\ProgressReportable;

/**
 * @internal
 */
final class Import
{
    use Queueable;
    use ProgressReportable;

    /**
     * @var string
     */
    private $searchable;

    /**
     * @param string $searchable
     */
    public function __construct(string $searchable)
    {
        $this->searchable = $searchable;
    }

    /**
     * @param Client $elasticsearch
     */
    public function handle(Client $elasticsearch): void
    {
        $stages = $this->stages();
        $estimate = $stages->sum->estimate();
        $this->progressBar()->setMaxSteps($estimate);
        $stages->each(function ($stage) use ($elasticsearch) {
            $this->progressBar()->setMessage($stage->title());
            $stage->handle($elasticsearch);
            $this->progressBar()->advance($stage->estimate());
        });
    }

    private function stages(): Collection
    {
        return ImportStages::fromSearchable(new $this->searchable);
    }
}
