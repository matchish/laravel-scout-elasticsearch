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
        $jobId = uniqid();
        $job = new Job([
            new ProgressReport(1, 'First', $jobId),
            new ProgressReport(1, 'Second', $jobId),
            new ProgressReport(3, 'Third', $jobId),
            new ProgressReport(2, 'Fourth', $jobId),
            new ProgressReport(1, 'Fitth', $jobId),
        ]);
        $output = new DummyOutput();
        $outputStyle = new OutputStyle(new ArrayInput([]), new DummyOutput());
        $sut = new JobProgress($job, $outputStyle, $events);
        $sut->start();
        while ($event = $job->next()) {
            $events->dispatch($event);
        }
        $sut->finish();
        $this->assertEquals([], $output->getLogs());
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

    public function next(): ProgressReport
    {
        return array_pop($this->stack);
    }
}
