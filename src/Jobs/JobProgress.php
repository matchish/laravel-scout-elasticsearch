<?php


namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Helper\ProgressBar;

class JobProgress
{
    /**
     * @var Dispatcher
     */
    private $events;
    /**
     * @var ProgressBar
     */
    private $bar;

    /**
     * @param ProgressBar $bar
     * @param Dispatcher $events
     */
    public function __construct(ProgressBar $bar, Dispatcher $events)
    {
        $this->events = $events;
        $this->bar = $bar;
    }

    public function start()
    {
        $this->events->listen(ProgressReport::class, function ($event) {
            if ($event->message) {
                $this->bar->setMessage($event->message);
            }
            $this->bar->advance($event->advance);
        });
        $this->bar->start();
    }

    public function finish()
    {
        $this->bar->finish();
    }
}
