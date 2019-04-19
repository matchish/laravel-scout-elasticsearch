<?php


namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Contracts\Events\Dispatcher;
use Matchish\ScoutElasticSearch\Console\Commands\DefaultProgressBar;

class JobProgress
{
    /**
     * @var Dispatcher
     */
    private $events;
    /**
     * @var DefaultProgressBar
     */
    private $bar;

    /**
     * @param DefaultProgressBar $bar
     * @param Dispatcher $events
     */
    public function __construct(DefaultProgressBar $bar, Dispatcher $events)
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
