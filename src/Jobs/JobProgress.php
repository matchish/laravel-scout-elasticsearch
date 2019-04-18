<?php


namespace Matchish\ScoutElasticSearch\Jobs;


use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class JobProgress
{
    private $job;
    /**
     * @var OutputStyle
     */
    private $output;
    /**
     * @var Dispatcher
     */
    private $events;
    /**
     * @var ProgressBar
     */
    private $bar;

    /**
     * @param $job
     * @param OutputInterface $output
     * @param Dispatcher $events
     */
    public function __construct($job, OutputStyle $output, Dispatcher $events)
    {
        $this->job = $job;
        $this->output = $output;
        $this->events = $events;
    }

    public function start()
    {
        $estimate = $this->job->estimate();
        $bar = $this->createProgressBar($estimate);
        $this->bar = $bar;
        $this->events->listen(JobReport::class, function ($event) {
            $this->bar->setMessage($event->message);
            $this->bar->advance($event->advance);
        });
        $this->bar->start();
    }

    private function createProgressBar($estimate): ProgressBar
    {
        $bar = $this->output->createProgressBar($estimate);
        $bar->setBarCharacter('<fg=green>⚬</>');
        $bar->setEmptyBarCharacter("<fg=red>⚬</>");
        $bar->setProgressCharacter("<fg=green>➤</>");
        $bar->setFormat(
            "%message%\n%current%/%max% [%bar%] %percent:3s%%\n"
        );
        return $bar;
    }

    public function finish()
    {
        $this->bar->finish();
    }
}
