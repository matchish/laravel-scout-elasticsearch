<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tests\Fixtures\DummyOutput;
use Tests\TestCase;

class JobProgressTest extends TestCase
{

    public function testJobProgress()
    {
        $events = app('events');
        $job = new Job([
            new ProgressReport(1, 'First step'),
            new ProgressReport(2, 'Second step'),
            new ProgressReport(1, 'Third'),
            new ProgressReport(3, 'Next'),
            new ProgressReport(1, 'Last'),
        ]);
        $output = new DummyOutput();
        $outputStyle = new OutputStyle(new ArrayInput([]), $output);
        $progressBar = $outputStyle->createProgressBar();
        $progressBar->setMaxSteps($job->estimate() + 3);
        $progressBar->setFormat('[%message%] %current%/%max%');
        $progressBar->setMessage('Start');
        $sut = new JobProgress($progressBar, $events);
        $sut->start();
        while ($event = $job->next()) {
            $events->dispatch($event);
        }
        $sut->finish();
        $this->assertEquals([
            'Start  0/11',
            'First step  1/11',
            'Second step  3/11',
            'Third  4/11',
            'Next  7/11',
            'Last  8/11',
            'Last 11/11',
        ], $output->getLogs());
    }
}

class Job
{
    /**
     * @var array
     */
    private $stack;

    /**
     * @param array $stack
     */
    public function __construct(array $stack)
    {
        $this->stack = $stack;
    }

    public function estimate(): int
    {
        $estimate = 0;
        foreach ($this->stack as $item) {
            $estimate += $item->advance;
        }
        return $estimate;
    }

    public function next()
    {
        return array_shift($this->stack);
    }
}
