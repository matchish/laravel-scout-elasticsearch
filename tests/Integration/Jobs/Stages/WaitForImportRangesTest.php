<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs\Stages;

use App\Product;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Schema;
use Matchish\ScoutElasticSearch\Jobs\ImportStages;
use Matchish\ScoutElasticSearch\Jobs\Stages\DispatchImportRanges;
use Matchish\ScoutElasticSearch\Jobs\Stages\WaitForImportRanges;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Tests\IntegrationTestCase;

final class WaitForImportRangesTest extends IntegrationTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Range jobs must stay pending, so the stage has something to
        // wait for; the null driver accepts and discards them.
        $app['config']->set('queue.connections.null', ['driver' => 'null']);
        $app['config']->set('queue.default', 'null');
        $app['config']->set('scout.chunk.searchable', 3);
        $app['config']->set('elasticsearch.parallel.chunks_per_range', 1);
    }

    public function test_advances_as_range_jobs_finish(): void
    {
        [$dispatch, $wait] = $this->dispatchedStages(9);
        $repository = app(BatchRepository::class);
        $batchId = $dispatch->batchId();
        $this->assertNotNull($batchId);
        $total = $repository->find($batchId)->totalJobs;
        $this->assertSame(3, $total);

        $wait->handle();
        $this->assertFalse($wait->completed(), 'nothing finished yet');
        $this->assertSame(0, $wait->advance());

        $repository->decrementPendingJobs($batchId, 'job-1');
        $wait->handle();
        $this->assertSame(1, $wait->advance(), 'one job finished since the last poll');
        $this->assertFalse($wait->completed());

        $repository->decrementPendingJobs($batchId, 'job-2');
        $repository->decrementPendingJobs($batchId, 'job-3');
        $repository->markAsFinished($batchId);
        $wait->handle();
        $this->assertSame(2, $wait->advance());
        $this->assertTrue($wait->completed());
    }

    public function test_failed_range_jobs_stop_the_import_with_an_explanation(): void
    {
        [$dispatch, $wait] = $this->dispatchedStages(9);
        $repository = app(BatchRepository::class);
        $batchId = $dispatch->batchId();
        $this->assertNotNull($batchId);

        $repository->incrementFailedJobs($batchId, 'job-1');
        $repository->decrementPendingJobs($batchId, 'job-1');
        $repository->cancel($batchId);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/1 of 3 range jobs failed.*alias was not switched/s');

        $wait->handle();
    }

    public function test_superseded_import_stops_instead_of_reporting_success(): void
    {
        [$dispatch, $wait] = $this->dispatchedStages(9);
        $batchId = $dispatch->batchId();
        $this->assertNotNull($batchId);

        app(BatchRepository::class)->cancel($batchId);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/cancelled.*newer import.*alias was not changed/s');

        $wait->handle();
    }

    public function test_missing_batches_table_explains_how_to_create_it(): void
    {
        Schema::drop('job_batches');

        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        factory(Product::class, 3)->create();
        Product::setEventDispatcher($dispatcher);

        $stages = ImportStages::fromSource(
            DefaultImportSourceFactory::from(Product::class),
            parallel: true,
        );
        $dispatch = $stages->first(function ($stage) {
            return $stage instanceof DispatchImportRanges;
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/queue:batches-table/');

        $dispatch->handle($this->elasticsearch);
    }

    public function test_completes_immediately_when_the_source_is_empty(): void
    {
        [, $wait] = $this->dispatchedStages(0);

        $wait->handle();

        $this->assertTrue($wait->completed());
        $this->assertSame(0, $wait->advance());
    }

    /**
     * @return array{0: DispatchImportRanges, 1: WaitForImportRanges}
     */
    private function dispatchedStages(int $productsAmount): array
    {
        if ($productsAmount > 0) {
            $dispatcher = Product::getEventDispatcher();
            Product::unsetEventDispatcher();
            factory(Product::class, $productsAmount)->create();
            Product::setEventDispatcher($dispatcher);
        }

        $stages = ImportStages::fromSource(
            DefaultImportSourceFactory::from(Product::class),
            parallel: true,
            watch: true,
        );
        $dispatch = $stages->first(function ($stage) {
            return $stage instanceof DispatchImportRanges;
        });
        $wait = $stages->first(function ($stage) {
            return $stage instanceof WaitForImportRanges;
        });
        $this->assertInstanceOf(DispatchImportRanges::class, $dispatch);
        $this->assertInstanceOf(WaitForImportRanges::class, $wait);

        $dispatch->handle($this->elasticsearch);

        return [$dispatch, $wait];
    }
}
