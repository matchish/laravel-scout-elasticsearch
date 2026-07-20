<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use App\Product;
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

    private function runJob(string $class, Range $range, string $column, int $chunkSize = 500): void
    {
        try {
            $this->elasticsearch->indices()->create(['index' => self::INDEX]);
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
