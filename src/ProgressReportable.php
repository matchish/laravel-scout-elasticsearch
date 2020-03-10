<?php

namespace Matchish\ScoutElasticSearch;

use Symfony\Component\Console\Helper\ProgressBar;

trait ProgressReportable
{
    /**
     * @var ProgressBar
     */
    private $progressBar;

    public function withProgressReport(ProgressBar $progressBar): void
    {
        $this->progressBar = $progressBar;
    }

    private function progressBar(): ProgressBar
    {
        return $this->progressBar;
    }
}
