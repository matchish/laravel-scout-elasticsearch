<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Product;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\Jobs\ImportStages;
use Matchish\ScoutElasticSearch\Jobs\ImportState;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Jobs\Stages\DispatchImportRanges;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\IntegrationTestCase;

final class ImportStatusCommandTest extends IntegrationTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // The null driver accepts jobs and discards them, so the import
        // stays unfinished for as long as a test needs.
        $app['config']->set('queue.connections.null', ['driver' => 'null']);
        $app['config']->set('queue.default', 'null');
        // 9 products, 3 per chunk and 1 chunk per range: 3 ranges.
        $app['config']->set('scout.chunk.searchable', 3);
        $app['config']->set('elasticsearch.parallel.chunks_per_range', 1);
    }

    public function test_reports_when_no_import_ran_yet(): void
    {
        $status = $this->importStatus();

        $this->assertStringContainsString('products', $status);
        $this->assertStringContainsString('no import found', $status);
    }

    public function test_reports_progress_in_recorded_ranges(): void
    {
        [, $state] = $this->startImport();
        $state->recordFinished($this->elasticsearch, 0);

        $status = $this->importStatus();

        $this->assertStringContainsString('running', $status);
        $this->assertStringContainsString('33%', $status);
        $this->assertStringContainsString('1/3', $status);
    }

    public function test_counts_a_range_once_however_often_its_job_ran(): void
    {
        [$batch, $state] = $this->startImport();
        // The queue delivers the job of range 0 three times, and the
        // batch counts each delivery as a finished job.
        foreach (['first', 'second', 'third'] as $delivery) {
            $batch->recordSuccessfulJob('range-0-'.$delivery);
            $state->recordFinished($this->elasticsearch, 0);
        }

        $status = $this->importStatus();

        $this->assertStringContainsString('running', $status);
        $this->assertStringContainsString('33%', $status);
        $this->assertStringContainsString('1/3', $status);
    }

    public function test_reports_switching_until_the_final_step_publishes(): void
    {
        [, $state] = $this->startImport();
        foreach ([0, 1, 2] as $range) {
            $state->recordFinished($this->elasticsearch, $range);
        }

        $status = $this->importStatus();

        $this->assertStringContainsString('switching', $status);
        $this->assertStringContainsString('100%', $status);
        $this->assertStringContainsString('3/3', $status);
    }

    public function test_reports_finished_once_searches_use_the_new_index(): void
    {
        // A sync queue runs the range jobs and the final step at once.
        $this->startImport('sync');

        $status = $this->importStatus();

        $this->assertStringContainsString('finished', $status);
        $this->assertStringContainsString('100%', $status);
        $this->assertStringContainsString('3/3', $status);
    }

    public function test_reports_why_the_final_step_failed(): void
    {
        [, $state] = $this->startImport();
        foreach ([0, 1, 2] as $range) {
            $state->recordFinished($this->elasticsearch, $range);
        }
        $state->recordFailure($this->elasticsearch, 'cluster is read-only');

        $status = $this->importStatus();

        $this->assertStringContainsString('failed', $status);
        $this->assertStringContainsString('products: the final step failed: cluster is read-only.', $status);
    }

    public function test_reports_why_range_jobs_failed(): void
    {
        [$batch] = $this->startImport();
        // A range job failed on its last try, so the batch cancels itself.
        $batch->recordFailedJob('range-1', new \RuntimeException('connection refused'));

        $status = $this->importStatus();

        $this->assertStringContainsString('failed', $status);
        $this->assertStringContainsString('products: 1 of 3 range jobs failed', $status);
    }

    public function test_a_cancelled_import_with_every_range_recorded_stays_cancelled(): void
    {
        [$batch, $state] = $this->startImport();
        foreach ([0, 1, 2] as $range) {
            $state->recordFinished($this->elasticsearch, $range);
        }
        // The final step stands down for a cancelled batch.
        $batch->cancel();

        $status = $this->importStatus();

        $this->assertStringContainsString('cancelled', $status);
        $this->assertStringNotContainsString('switching', $status);
    }

    public function test_reports_cancelled_when_a_newer_import_removed_it(): void
    {
        $this->startImport();
        // An import without --parallel does not cancel the batch. It
        // removes this import's index and state when it cleans up.
        (new CleanUp(DefaultImportSourceFactory::from(Product::class)))->handle($this->elasticsearch);

        $status = $this->importStatus();

        $this->assertStringContainsString('cancelled', $status);
    }

    private function importStatus(): string
    {
        $output = new BufferedOutput();
        Artisan::call('scout:import:status', ['searchable' => [Product::class]], $output);

        return $output->fetch();
    }

    /**
     * Imports 9 products in parallel without waiting for the jobs.
     *
     * @return array{0: Batch, 1: ImportState}
     */
    private function startImport(string $queue = 'null'): array
    {
        $this->app['config']->set('queue.default', $queue);
        Product::withoutEvents(function () {
            factory(Product::class, 9)->create();
        });

        $stages = ImportStages::fromSource(DefaultImportSourceFactory::from(Product::class), parallel: true);
        foreach ($stages as $stage) {
            $stage->handle($this->elasticsearch);
        }

        $dispatch = $stages->first(function ($stage) {
            return $stage instanceof DispatchImportRanges;
        });
        $this->assertInstanceOf(DispatchImportRanges::class, $dispatch);
        $batch = Bus::findBatch((string) $dispatch->batchId());
        $state = $dispatch->state();
        $this->assertNotNull($batch);
        $this->assertNotNull($state);

        return [$batch, $state];
    }
}
