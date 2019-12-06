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
            'Clean up 1/7',
            'Create write index 2/7',
            'Indexing... 3/7',
            'Indexing... 4/7',
            'Indexing... 5/7',
            'Refreshing index 6/7',
            'Switching to the new index 7/7',
        ], $output->getLogs());
    }
}
