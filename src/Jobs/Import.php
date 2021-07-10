<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Elasticsearch\Client;
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
     * @param ImportSource $source
     */
    public function __construct(ImportSource $source)
    {
        $this->source = $source;
    }

    /**
     * @param Client $elasticsearch
     */
    public function handle(Client $elasticsearch): void
    {
        $stages = $this->stages();
        $estimate = $stages->sum->estimate();
        $progressbar = $this->progressBar();
        $progressbar->setMaxSteps($estimate);

        $stages->each(function ($stage) use ($elasticsearch, $progressbar) {
            $progressbar->setMessage($stage->title());
            $progress = $stage->handle($elasticsearch);
            if ($progress) {
                $currentStep = $progressbar->getProgress();
                foreach ($progress as $step) {
                    $progressbar->advance($step);
                }
                $progressbar->setProgress((int) ($currentStep + $stage->estimate()));
            } else {
                $progressbar->advance($stage->estimate());
            }
        });
    }

    private function stages(): Collection
    {
        return ImportStages::fromSource($this->source);
    }
}
