<?php

namespace Matchish\ScoutElasticSearch;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Helper\ProgressBar;

trait ProgressReportable
{
    private $progressBar;

    public function withProgressReport(ProgressBar $progressBar)
    {
        $this->progressBar = $progressBar;
    }

    private function progressBar(): ProgressBar
    {
        return $this->progressBar ?: new ProgressBar(new NullOutput());
    }
}
