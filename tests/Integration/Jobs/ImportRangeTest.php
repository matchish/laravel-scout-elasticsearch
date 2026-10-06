<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use App\Product;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\Jobs\ImportRange;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Matchish\ScoutElasticSearch\Searchable\Partitionable;
use Matchish\ScoutElasticSearch\Searchable\Range;
use stdClass;
use Tests\Fixtures\ProductWithPartitionKey;
use Tests\IntegrationTestCase;

final class ImportRangeTest extends IntegrationTestCase
{
    private const INDEX = 'products_import';

    public function test_imports_only_rows_within_its_range(): void
    {
        $ids = $this->createProducts(10);
        $from = $ids[0];
        $to = $ids[5];

        $this->runJob(Product::class, Range::between($from, $to), 'id');

        $this->assertSame(
            array_map('strval', array_slice($ids, 0, 5)),
            $this->indexedIds()
        );
    }

    public function test_open_ended_range_imports_to_the_end(): void
    {
        $ids = $this->createProducts(10);
        $from = $ids[5];

        $this->runJob(Product::class, Range::between($from, null), 'id');

        $this->assertSame(
            array_map('strval', array_slice($ids, 5)),
            $this->indexedIds()
        );
    }

    public function test_pages_through_range_larger_than_chunk_size(): void
    {
        $ids = $this->createProducts(10);

        $this->runJob(Product::class, Range::between($ids[0], null), 'id', 3);

        $this->assertCount(10, $this->indexedIds());
    }

    public function test_duplicate_partition_values_across_chunks_are_not_skipped(): void
    {
        $this->createProducts(9, ['weight' => 100]);

        $this->runJob(ProductWithPartitionKey::class, Range::between(100, null), 'weight', 3);

        $this->assertCount(9, $this->indexedIds());
    }

    public function test_null_bucket_imports_only_null_partition_rows(): void
    {
        $this->createProducts(4, ['weight' => null]);
        $this->createProducts(3, ['weight' => 100]);

        $this->runJob(ProductWithPartitionKey::class, Range::nullBucket(), 'weight', 3);

        $this->assertCount(4, $this->indexedIds());
    }

    public function test_skips_rows_that_should_not_be_searchable(): void
    {
        $ids = $this->createProducts(3);
        $this->createProducts(2, ['type' => 'archive']);

        $this->runJob(Product::class, Range::between($ids[0], null), 'id');

        $this->assertCount(3, $this->indexedIds());
    }

    public function test_rows_deleted_after_planning_do_not_block_completion(): void
    {
        $ids = $this->createProducts(10);
        \Illuminate\Support\Facades\DB::table('products')
            ->whereIn('id', array_slice($ids, 5))
            ->delete();

        $this->runJob(Product::class, Range::between($ids[0], null), 'id', 3);

        $this->assertCount(5, $this->indexedIds());
    }

    public function test_cancelled_batch_stops_the_job_before_importing(): void
    {
        $this->createProducts(5);
        /** @var \Illuminate\Bus\BatchRepository $repository */
        $repository = app(\Illuminate\Bus\BatchRepository::class);
        $batch = $repository->store(\Illuminate\Support\Facades\Bus::batch([])->name('scout-import:products'));
        $repository->cancel($batch->id);

        try {
            $this->elasticsearch->indices()->create(['index' => self::INDEX]);
        } catch (\Exception $e) {
            // index exists
        }
        $source = DefaultImportSourceFactory::from(Product::class);
        $job = new ImportRange($source, Range::between(1, null), 'id', self::INDEX, 500);
        $job->withBatchId($batch->id);
        $job->handle($this->elasticsearch);

        $this->assertCount(0, $this->indexedIds());
    }

    public function test_revoked_write_target_stops_the_job_and_creates_nothing(): void
    {
        $ids = $this->createProducts(5);
        // No index and no alias exist: the import was superseded and its
        // index deleted. Without require_alias this write would silently
        // auto-create the index again.
        $source = DefaultImportSourceFactory::from(Product::class);
        $job = new ImportRange($source, Range::between($ids[0], null), 'id', self::INDEX, 500);
        $job->handle($this->elasticsearch);

        $this->assertFalse(
            $this->elasticsearch->indices()->exists(['index' => self::INDEX])->asBool(),
            'a rejected write must not resurrect the index'
        );
    }

    public function test_writes_to_the_concrete_index_not_the_alias(): void
    {
        $this->elasticsearch->indices()->create([
            'index' => 'products_live',
            'body' => ['aliases' => ['products' => new stdClass()]],
        ]);
        $ids = $this->createProducts(3);

        $this->runJob(Product::class, Range::between($ids[0], null), 'id');

        $this->elasticsearch->indices()->refresh(['index' => '_all']);
        $aliased = $this->elasticsearch->search([
            'index' => 'products',
            'body' => ['query' => ['match_all' => new stdClass()]],
        ]);
        $this->assertSame(0, $aliased['hits']['total']['value']);
        $this->assertCount(3, $this->indexedIds());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, int>
     */
    private function createProducts(int $amount, array $attributes = []): array
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        $products = factory(Product::class, $amount)->create($attributes);
        Product::setEventDispatcher($dispatcher);

        return $products->pluck('id')->map(function ($id) {
            return (int) $id;
        })->values()->all();
    }

    /**
     * The job writes through an import alias with require_alias, the
     * way DispatchImportRanges wires it, so the tests create the
     * backing index with that alias attached.
     */
    public function test_hands_the_rest_of_a_long_range_to_new_jobs_in_its_batch(): void
    {
        $ids = $this->createProducts(10);
        $this->createWriteTarget();

        // Chunks of 3 and at most one chunk per job: 10 rows need four
        // jobs, and no single job sees more than one chunk.
        $batch = Bus::batch([new ImportRange(
            DefaultImportSourceFactory::from(Product::class),
            Range::between($ids[0], null),
            'id',
            self::INDEX,
            3,
            1
        )])->onConnection('sync')->dispatch();

        $this->assertCount(10, $this->indexedIds(), 'every row of the range is imported');
        $finished = Bus::findBatch($batch->id);
        $this->assertNotNull($finished);
        $this->assertSame(4, $finished->totalJobs, 'the range was handed on three times');
        $this->assertSame(0, $finished->pendingJobs);
    }

    public function test_a_range_of_exactly_one_jobs_size_needs_no_second_job(): void
    {
        // Two full chunks of 3, and at most two chunks per job: the last
        // chunk is full, but nothing is left after it.
        $ids = $this->createProducts(6);
        $this->createWriteTarget();

        $batch = Bus::batch([new ImportRange(
            DefaultImportSourceFactory::from(Product::class),
            Range::between($ids[0], null),
            'id',
            self::INDEX,
            3,
            2
        )])->onConnection('sync')->dispatch();

        $this->assertCount(6, $this->indexedIds());
        $finished = Bus::findBatch($batch->id);
        $this->assertNotNull($finished);
        $this->assertSame(1, $finished->totalJobs, 'no empty job is added after the last row');
    }

    public function test_outside_a_batch_imports_the_whole_range_in_one_go(): void
    {
        $ids = $this->createProducts(10);
        $this->createWriteTarget();

        // No batch to hand work on to, so the limit does not apply.
        (new ImportRange(
            DefaultImportSourceFactory::from(Product::class),
            Range::between($ids[0], null),
            'id',
            self::INDEX,
            3,
            1
        ))->handle($this->elasticsearch);

        $this->assertCount(10, $this->indexedIds());
    }

    private function createWriteTarget(): void
    {
        $this->elasticsearch->indices()->create([
            'index' => self::INDEX.'_index',
            'body' => ['aliases' => [self::INDEX => new stdClass()]],
        ]);
    }

    private function runJob(string $class, Range $range, string $column, int $chunkSize = 500): void
    {
        try {
            $this->elasticsearch->indices()->create([
                'index' => self::INDEX.'_index',
                'body' => ['aliases' => [self::INDEX => new stdClass()]],
            ]);
        } catch (\Exception $e) {
            // index exists from an earlier call in the same test
        }
        $source = DefaultImportSourceFactory::from($class);
        $this->assertInstanceOf(Partitionable::class, $source);
        $job = new ImportRange($source, $range, $column, self::INDEX, $chunkSize);
        $job->handle($this->elasticsearch);
    }

    /**
     * @return array<int, string>
     */
    private function indexedIds(): array
    {
        $this->elasticsearch->indices()->refresh(['index' => self::INDEX]);
        $response = $this->elasticsearch->search([
            'index' => self::INDEX,
            'body' => [
                'size' => 100,
                'query' => ['match_all' => new stdClass()],
            ],
        ]);

        return collect($response['hits']['hits'])->pluck('_id')->sort(SORT_NATURAL)->values()->all();
    }
}
