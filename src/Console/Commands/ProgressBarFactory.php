<?php

namespace Matchish\ScoutElasticSearch\Console\Commands;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\OutputStyle;

class ProgressBarFactory
{
    /**
     * @var OutputStyle
     */
    private $output;

    /**
     * @param OutputStyle $output
     */
    public function __construct(OutputStyle $output)
    {
        $this->output = $output;
    }

    public function create(int $max = 0): ProgressBar
    {
        $bar = $this->output->createProgressBar($max);
        $bar->setBarCharacter('<fg=green>⚬</>');
        $bar->setEmptyBarCharacter('<fg=red>⚬</>');
        $bar->setProgressCharacter('<fg=green>➤</>');
        $bar->setRedrawFrequency(1);
        $bar->maxSecondsBetweenRedraws(0);
        $bar->minSecondsBetweenRedraws(0);
        $bar->setFormat(
            "%message%\n%current%/%max% [%bar%] %percent:3s%%\n"
        );

        return $bar;
    }
}
