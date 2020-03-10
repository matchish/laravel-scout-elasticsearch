<?php

namespace Matchish\ScoutElasticSearch;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;

trait ProgressReportable
{
    /**
     * @var ProgressBar|null
     */
    private $progressBar;

    public function withProgressReport(ProgressBar $progressBar): void
    {
        $this->progressBar = $progressBar;
    }

    private function progressBar(): ProgressBar
    {
        return $this->progressBar ?: new ProgressBar(new NullOutput());
    }
}
