<?php

namespace Tests\Integration\Jobs;

use App\Product;
use Illuminate\Console\OutputStyle;
use Matchish\ScoutElasticSearch\Jobs\Import;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Symfony\Component\Console\Input\ArrayInput;
use Tests\Fixtures\DummyOutput;
use Tests\IntegrationTestCase;

class ImportTest extends IntegrationTestCase
{
    public function test_progress_report()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        $productsAmount = 7;
        factory(Product::class, $productsAmount)->create();
        Product::setEventDispatcher($dispatcher);

        $job = new Import(DefaultImportSourceFactory::from(Product::class));
        $output = new DummyOutput();
        $outputStyle = new OutputStyle(new ArrayInput([]), $output);
        $progressBar = $outputStyle->createProgressBar();
        $progressBar->setRedrawFrequency(1);
        $progressBar->maxSecondsBetweenRedraws(0);
        $progressBar->minSecondsBetweenRedraws(0);
        $progressBar->setFormat('[%message%] %current%/%max%');
        $job->withProgressReport($progressBar);

        dispatch($job);

        $this->assertEquals([
            'Clean up 1/8',
            'Create write index 2/8',
            'Indexing... 3/8',
            'Indexing... 4/8',
            'Indexing... 5/8',
            'Indexing... 6/8',
            'Refreshing index 7/8',
            'Switching to the new index 8/8',
        ], $output->getLogs());
    }

    public function test_progress_report_parallel()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        $productsAmount = 7;
        factory(Product::class, $productsAmount)->create();
        Product::setEventDispatcher($dispatcher);

        $job = new Import(DefaultImportSourceFactory::from(Product::class));
        $job->parallel = true;
        $output = new DummyOutput();
        $outputStyle = new OutputStyle(new ArrayInput([]), $output);
        $progressBar = $outputStyle->createProgressBar();
        $progressBar->setRedrawFrequency(1);
        $progressBar->maxSecondsBetweenRedraws(0);
        $progressBar->minSecondsBetweenRedraws(0);
        $progressBar->setFormat('[%message%] %current%/%max%');
        $job->withProgressReport($progressBar);

        dispatch($job);

        $this->assertEquals([
            'Stop previous import 1/4',
            'Clean up 2/4',
            'Create write index 3/4',
            'Planning ranges 4/4',
        ], $output->getLogs());
    }

    public function test_progress_report_parallel_watching_ranges()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        $productsAmount = 7;
        factory(Product::class, $productsAmount)->create();
        Product::setEventDispatcher($dispatcher);

        $job = new Import(DefaultImportSourceFactory::from(Product::class));
        $job->parallel = true;
        $job->watchProgress = true;
        $output = new DummyOutput();
        $outputStyle = new OutputStyle(new ArrayInput([]), $output);
        $progressBar = $outputStyle->createProgressBar();
        $progressBar->setRedrawFrequency(1);
        $progressBar->maxSecondsBetweenRedraws(0);
        $progressBar->minSecondsBetweenRedraws(0);
        $progressBar->setFormat('[%message%] %current%/%max%');
        $job->withProgressReport($progressBar);

        dispatch($job);

        // One range covers all 7 products, so the bar reaches 5 of 5
        // only after that range job reports back.
        $this->assertEquals([
            'Stop previous import 1/5',
            'Clean up 2/5',
            'Create write index 3/5',
            'Planning ranges 4/5',
            'Indexing... 5/5',
        ], $output->getLogs());
    }
}
