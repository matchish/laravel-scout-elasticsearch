<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use App\Product;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Matchish\ScoutElasticSearch\Jobs\FinishImport;
use Matchish\ScoutElasticSearch\Jobs\ImportRange;
use Matchish\ScoutElasticSearch\Jobs\ImportStages;
use Matchish\ScoutElasticSearch\Jobs\Stages\DispatchImportRanges;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use stdClass;
use Tests\IntegrationTestCase;

/**
 * Any Laravel queue may deliver a job twice, and the batch counts the
 * second delivery as another finished job. The import must still
 * publish only once every range has written its rows, and only once.
 */
final class RepeatedDeliveryTest extends IntegrationTestCase
{
    public function test_a_job_counted_twice_does_not_publish_an_incomplete_import(): void
    {
        [$dispatch, $batchId, $ranges] = $this->dispatched(9);
        $this->assertCount(3, $ranges, 'nine products in chunks of three, one chunk per range');

        // The worker runs range 0, and the queue delivers it a second time.
        $this->work($ranges[0], $batchId, 'delivery-1');
        $this->work($ranges[0], $batchId, 'delivery-2');
        // Range 1 brings the pending count to zero: the batch is "done".
        $this->work($ranges[1], $batchId, 'delivery-3');

        $batch = Bus::findBatch($batchId);
        $this->assertNotNull($batch);
        $this->assertSame(0, $batch->pendingJobs, 'the batch believes every job has finished');
        Queue::assertNotPushed(FinishImport::class);

        // Range 2 finally runs; it records the last range.
        $this->work($ranges[2], $batchId, 'delivery-4');

        $finishing = Queue::pushed(FinishImport::class)->values()->all();
        $this->assertCount(1, $finishing);
        $finishing[0]->handle($this->elasticsearch);

        $this->assertSame(9, $this->searchableCount(), 'every range has written its rows');
        $state = $dispatch->state();
        $this->assertNotNull($state);
        $this->assertFalse($state->exists($this->elasticsearch), 'the state is removed once published');
    }

    public function test_a_repeat_after_publishing_starts_nothing(): void
    {
        [, $batchId, $ranges] = $this->dispatched(6);
        foreach ($ranges as $number => $range) {
            $this->work($range, $batchId, 'delivery-'.$number);
        }
        $finishing = Queue::pushed(FinishImport::class)->values()->all();
        $this->assertCount(1, $finishing);
        $finishing[0]->handle($this->elasticsearch);

        // The queue delivers the last range job once more.
        $this->work($ranges[count($ranges) - 1], $batchId, 'late-delivery');

        $this->assertCount(1, Queue::pushed(FinishImport::class), 'the import is finished by one job only');
        $this->assertSame(6, $this->searchableCount());
    }

    /**
     * Runs the stages that start a parallel import, with range jobs
     * held in a fake queue.
     *
     * @return array{0: DispatchImportRanges, 1: string, 2: array<int, ImportRange>}
     */
    private function dispatched(int $products): array
    {
        Queue::fake();
        $this->app['config']->set('queue.default', 'database');
        $this->app['config']->set('elasticsearch.parallel.chunks_per_range', 1);
        Product::withoutEvents(function () use ($products) {
            factory(Product::class, $products)->create();
        });

        $stages = ImportStages::fromSource(DefaultImportSourceFactory::from(Product::class), parallel: true);
        foreach ($stages as $stage) {
            $stage->handle($this->elasticsearch);
        }
        $dispatch = $stages->first(function ($stage) {
            return $stage instanceof DispatchImportRanges;
        });
        $this->assertInstanceOf(DispatchImportRanges::class, $dispatch);
        $batchId = $dispatch->batchId();
        $this->assertNotNull($batchId);
        /** @var array<int, ImportRange> $ranges */
        $ranges = Queue::pushed(ImportRange::class)->values()->all();

        return [$dispatch, $batchId, $ranges];
    }

    /**
     * What a queue worker does with one delivery of a batched job.
     */
    private function work(ImportRange $job, string $batchId, string $deliveryId): void
    {
        $job->handle($this->elasticsearch);
        $batch = Bus::findBatch($batchId);
        $this->assertNotNull($batch);
        $batch->recordSuccessfulJob($deliveryId);
    }

    private function searchableCount(): int
    {
        $this->elasticsearch->indices()->refresh(['index' => '_all']);
        $response = $this->elasticsearch->search([
            'index' => 'products',
            'body' => ['query' => ['match_all' => new stdClass()]],
        ])->asArray();

        return (int) $response['hits']['total']['value'];
    }
}
