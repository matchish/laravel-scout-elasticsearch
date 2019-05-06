<?php

namespace Matchish\ScoutElasticSearch\Console\Commands;

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

    public function create(int $max = 0)
    {
        $bar = $this->output->createProgressBar($max);
        $bar->setBarCharacter('<fg=green>⚬</>');
        $bar->setEmptyBarCharacter('<fg=red>⚬</>');
        $bar->setProgressCharacter('<fg=green>➤</>');
        $bar->setFormat(
            "%message%\n%current%/%max% [%bar%] %percent:3s%%\n"
        );

        return $bar;
    }
}
