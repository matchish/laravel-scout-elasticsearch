<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\ProgressReportable;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @internal
 */
final class Import
{
    use Queueable;
    use ProgressReportable;

    /**
     * @var ImportSource
     */
    private $source;

    /**
     * @param  ImportSource  $source
     */
    public function __construct(ImportSource $source)
    {
        $this->source = $source;
    }

    /**
     * @param  Client  $elasticsearch
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
        return ImportStages::fromSource($this->source);
    }
}
