<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Product;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Artisan;
use Matchish\ScoutElasticSearch\Jobs\ImportStages;
use Matchish\ScoutElasticSearch\Jobs\Stages\DispatchImportRanges;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\IntegrationTestCase;

final class ImportStatusCommandTest extends IntegrationTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Range jobs stay pending, so a batch is observable.
        $app['config']->set('queue.connections.null', ['driver' => 'null']);
        $app['config']->set('queue.default', 'null');
        $app['config']->set('scout.chunk.searchable', 3);
        $app['config']->set('elasticsearch.parallel.chunks_per_range', 1);
    }

    public function test_reports_when_no_import_ran_yet(): void
    {
        $output = new BufferedOutput();
        Artisan::call('scout:import:status', ['searchable' => [Product::class]], $output);
        $text = $output->fetch();

        $this->assertStringContainsString('products', $text);
        $this->assertStringContainsString('no import found', $text);
    }

    public function test_reports_progress_of_a_running_import(): void
    {
        $batchId = $this->startImport(9);
        app(BatchRepository::class)->decrementPendingJobs($batchId, 'job-1');

        $output = new BufferedOutput();
        Artisan::call('scout:import:status', ['searchable' => [Product::class]], $output);
        $text = $output->fetch();

        $this->assertStringContainsString('running', $text);
        $this->assertStringContainsString('1/3', $text);
        $this->assertStringContainsString('33%', $text);
    }

    public function test_reports_failed_import(): void
    {
        $batchId = $this->startImport(9);
        $repository = app(BatchRepository::class);
        $repository->incrementFailedJobs($batchId, 'job-1');
        $repository->decrementPendingJobs($batchId, 'job-1');

        $output = new BufferedOutput();
        Artisan::call('scout:import:status', ['searchable' => [Product::class]], $output);

        $this->assertStringContainsString('failed', $output->fetch());
    }

    private function startImport(int $productsAmount): string
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        factory(Product::class, $productsAmount)->create();
        Product::setEventDispatcher($dispatcher);

        $stages = ImportStages::fromSource(
            DefaultImportSourceFactory::from(Product::class),
            parallel: true,
        );
        $dispatch = $stages->first(function ($stage) {
            return $stage instanceof DispatchImportRanges;
        });
        $this->assertInstanceOf(DispatchImportRanges::class, $dispatch);
        $dispatch->handle($this->elasticsearch);

        $batchId = $dispatch->batchId();
        $this->assertNotNull($batchId);

        return $batchId;
    }
}
