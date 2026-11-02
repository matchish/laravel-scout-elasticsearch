<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs\Stages;

use App\Product;
use Illuminate\Bus\BatchRepository;
use Matchish\ScoutElasticSearch\Jobs\ImportStages;
use Matchish\ScoutElasticSearch\Jobs\Stages\CancelPreviousImport;
use Matchish\ScoutElasticSearch\Jobs\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Jobs\Stages\DispatchImportRanges;
use Matchish\ScoutElasticSearch\Jobs\Stages\WaitForImportRanges;
use Matchish\ScoutElasticSearch\Jobs\Stages\WaitForPublish;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Tests\IntegrationTestCase;

final class WaitForPublishTest extends IntegrationTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // The null driver accepts jobs and discards them, so the import
        // stays unfinished for as long as a test needs.
        $app['config']->set('queue.connections.null', ['driver' => 'null']);
        $app['config']->set('scout.chunk.searchable', 3);
    }

    public function test_completes_once_the_alias_is_switched(): void
    {
        // A sync queue runs the range jobs and the finishing job at once.
        [, $publish] = $this->startImport(5, 'sync');

        $publish->handle($this->elasticsearch);

        $this->assertTrue($publish->completed());
    }

    public function test_an_import_with_no_rows_is_published_too(): void
    {
        [, $publish] = $this->startImport(0, 'sync');

        $publish->handle($this->elasticsearch);

        $this->assertTrue($publish->completed());
    }

    public function test_waits_while_the_finishing_job_has_not_run(): void
    {
        [, $publish] = $this->startImport(5, 'null');

        $publish->handle($this->elasticsearch);

        $this->assertFalse($publish->completed());
    }

    public function test_reports_why_the_finishing_job_failed(): void
    {
        [$dispatch, $publish] = $this->startImport(5, 'null');
        $state = $dispatch->state();
        $this->assertNotNull($state);
        $state->recordFailure($this->elasticsearch, 'cluster is read-only');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/could not be published: cluster is read-only.*old index still serves searches/s');

        $publish->handle($this->elasticsearch);
    }

    public function test_reports_a_newer_import_that_replaced_this_one(): void
    {
        [$dispatch, $publish] = $this->startImport(5, 'null');
        $batchId = $dispatch->batchId();
        $this->assertNotNull($batchId);
        // The batch already looked finished, so the newer import does not
        // cancel it; it removes this import's index and state instead.
        app(BatchRepository::class)->markAsFinished($batchId);
        $source = DefaultImportSourceFactory::from(Product::class);
        (new CancelPreviousImport($source))->handle($this->elasticsearch);
        (new CleanUp($source))->handle($this->elasticsearch);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/newer import.*replaced this one/s');

        $publish->handle($this->elasticsearch);
    }

    public function test_reports_a_cancelled_import(): void
    {
        [$dispatch, $publish] = $this->startImport(5, 'null');
        $batchId = $dispatch->batchId();
        $this->assertNotNull($batchId);
        app(BatchRepository::class)->cancel($batchId);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/cancelled.*alias was not changed/s');

        $publish->handle($this->elasticsearch);
    }

    /**
     * Runs every stage that comes before the console starts waiting.
     *
     * @return array{0: DispatchImportRanges, 1: WaitForPublish}
     */
    private function startImport(int $products, string $queue): array
    {
        $this->app['config']->set('queue.default', $queue);
        if ($products > 0) {
            Product::withoutEvents(function () use ($products) {
                factory(Product::class, $products)->create();
            });
        }

        $stages = ImportStages::fromSource(
            DefaultImportSourceFactory::from(Product::class),
            parallel: true,
            watch: true,
        );
        foreach ($stages as $stage) {
            if ($stage instanceof WaitForImportRanges || $stage instanceof WaitForPublish) {
                continue;
            }
            $stage->handle($this->elasticsearch);
        }

        $dispatch = $stages->first(function ($stage) {
            return $stage instanceof DispatchImportRanges;
        });
        $publish = $stages->first(function ($stage) {
            return $stage instanceof WaitForPublish;
        });
        $this->assertInstanceOf(DispatchImportRanges::class, $dispatch);
        $this->assertInstanceOf(WaitForPublish::class, $publish);

        return [$dispatch, $publish];
    }
}
