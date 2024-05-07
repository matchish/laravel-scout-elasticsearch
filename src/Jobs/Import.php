<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\Jobs\Stages\StageInterface;
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
     * @var boolean
     */
    public $parallel = false;

    public ?int $timeout = null;

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

        /** @var StageInterface $currentStage */
        $currentStage = $stages->shift();

        while ($currentStage !== null) {
            $this->progressBar()->setMessage($currentStage->title());
            $currentStage->handle($elasticsearch);
            $this->progressBar()->advance($currentStage->advance());
            if($currentStage->completed()) {
                if($stages->isEmpty()) {
                    /** @var null $currentStage */
                    $currentStage = null;
                } else {
                    /** @var StageInterface $currentStage */
                    $currentStage = $stages->shift();
                }
            }
        }
    }

    /**
     * @return Collection<int, StageInterface>
     */
    private function stages(): Collection
    {
        return ImportStages::fromSource($this->source, $this->parallel);
    }
}
