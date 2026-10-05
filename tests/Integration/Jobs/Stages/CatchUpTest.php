<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs\Stages;

use App\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Matchish\ScoutElasticSearch\ElasticSearch\ImportAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\ImportRange;
use Matchish\ScoutElasticSearch\Jobs\Stages\CatchUp;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Matchish\ScoutElasticSearch\Searchable\Range;
use stdClass;
use Tests\Fixtures\ProductWithoutTimestamps;
use Tests\IntegrationTestCase;

final class CatchUpTest extends IntegrationTestCase
{
    private const INDEX = 'products_import';

    /**
     * @group parallel-import-regressions
     */
    public function test_removes_a_row_that_became_unsearchable_after_its_range_was_imported(): void
    {
        $product = Product::withoutEvents(function () {
            return factory(Product::class)->create(['updated_at' => '2000-01-01 00:00:00']);
        });
        $this->elasticsearch->indices()->create([
            'index' => self::INDEX,
            'body' => ['aliases' => [ImportAlias::of(self::INDEX) => new stdClass()]],
        ]);
        $source = DefaultImportSourceFactory::from(Product::class);
        (new ImportRange($source, Range::between((int) $product->getKey(), null), 'id', ImportAlias::of(self::INDEX), 3))
            ->handle($this->elasticsearch);
        $this->assertSame(1, $this->indexedCount());

        // SQL writes bypass Scout observers, which is why catch-up is needed.
        DB::table('products')->where('id', $product->getKey())->update([
            'type' => 'archive',
            'updated_at' => '2000-01-02 00:00:00',
        ]);
        $stage = new CatchUp($source, new Index(self::INDEX), new \DateTimeImmutable('2000-01-01'));

        $stage->handle($this->elasticsearch);

        $this->assertSame(0, $this->indexedCount(), 'catch-up must remove a snapshot that is no longer searchable');
    }

    public function test_removes_a_row_soft_deleted_by_sql_after_its_range_was_imported(): void
    {
        $product = $this->importedProduct();

        // A raw soft delete that sets deleted_at alone: updated_at stays
        // before the catch-up window, so only deleted_at reveals it.
        DB::table('products')->where('id', $product->getKey())->update(['deleted_at' => '2000-01-02 00:00:00']);
        $stage = new CatchUp(
            DefaultImportSourceFactory::from(Product::class),
            new Index(self::INDEX),
            new \DateTimeImmutable('2000-01-01 12:00:00')
        );

        $stage->handle($this->elasticsearch);

        $this->assertSame(0, $this->indexedCount(), 'catch-up must remove a row trashed during the import');
    }

    public function test_marks_a_row_soft_deleted_by_sql_when_soft_deletes_stay_searchable(): void
    {
        $this->app['config']->set('scout.soft_delete', true);
        $product = $this->importedProduct();

        DB::table('products')->where('id', $product->getKey())->update(['deleted_at' => '2000-01-02 00:00:00']);
        $stage = new CatchUp(
            DefaultImportSourceFactory::from(Product::class),
            new Index(self::INDEX),
            new \DateTimeImmutable('2000-01-01 12:00:00')
        );

        $stage->handle($this->elasticsearch);

        $document = $this->elasticsearch->get([
            'index' => self::INDEX,
            'id' => (string) $product->getKey(),
        ])->asArray();
        $this->assertSame(1, $document['_source']['__soft_deleted'], 'catch-up must mark the row as soft deleted');
    }

    /**
     * @group parallel-import-regressions
     */
    public function test_preserves_a_live_update_from_the_same_second(): void
    {
        // Freeze the clock so both writes have the same second without sleeps.
        Carbon::setTestNow('2000-01-01 00:00:00');

        try {
            new Product();
            $writer = Product::withoutEvents(function () {
                return factory(Product::class)->create(['title' => 'old title']);
            });
            $this->elasticsearch->indices()->create([
                'index' => self::INDEX,
                'body' => ['aliases' => [
                    'products' => new stdClass(),
                    ImportAlias::of(self::INDEX) => new stdClass(),
                ]],
            ]);

            // The observer writes the new title after catch-up reads its copy,
            // but before catch-up submits that older copy to Elasticsearch.
            $updated = false;
            Product::retrieved(function (Product $model) use ($writer, &$updated) {
                if (! $updated && $model->getKey() === $writer->getKey()) {
                    $updated = true;
                    $writer->update(['title' => 'live title']);
                }
            });
            $stage = new CatchUp(
                DefaultImportSourceFactory::from(Product::class),
                new Index(self::INDEX),
                new \DateTimeImmutable('2000-01-01')
            );

            $stage->handle($this->elasticsearch);

            $this->assertSame('live title', DB::table('products')->where('id', $writer->getKey())->value('title'));
            $document = $this->elasticsearch->get([
                'index' => self::INDEX,
                'id' => (string) $writer->getKey(),
            ])->asArray();
            $this->assertSame('live title', $document['_source']['title'], 'catch-up must not roll back the live update');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_imports_only_rows_updated_since_the_given_time(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        $stale = factory(Product::class, 3)->create();
        $fresh = factory(Product::class, 2)->create();
        Product::setEventDispatcher($dispatcher);

        DB::table('products')
            ->whereIn('id', $stale->pluck('id'))
            ->update(['updated_at' => '2000-01-01 00:00:00']);

        $this->elasticsearch->indices()->create([
            'index' => self::INDEX,
            'body' => ['aliases' => [ImportAlias::of(self::INDEX) => new stdClass()]],
        ]);
        $stage = new CatchUp(
            DefaultImportSourceFactory::from(Product::class),
            new Index(self::INDEX),
            new \DateTimeImmutable('2000-01-02 00:00:00')
        );
        $stage->handle($this->elasticsearch);

        $this->assertSame(2, $this->indexedCount());
    }

    public function test_pages_through_more_rows_than_chunk_size(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        factory(Product::class, 10)->create();
        Product::setEventDispatcher($dispatcher);

        $this->elasticsearch->indices()->create([
            'index' => self::INDEX,
            'body' => ['aliases' => [ImportAlias::of(self::INDEX) => new stdClass()]],
        ]);
        $stage = new CatchUp(
            DefaultImportSourceFactory::from(Product::class),
            new Index(self::INDEX),
            new \DateTimeImmutable('2000-01-01 00:00:00')
        );
        $stage->handle($this->elasticsearch);

        $this->assertSame(10, $this->indexedCount());
    }

    public function test_skips_model_without_timestamps(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        factory(Product::class, 3)->create();
        Product::setEventDispatcher($dispatcher);

        $this->elasticsearch->indices()->create([
            'index' => self::INDEX,
            'body' => ['aliases' => [ImportAlias::of(self::INDEX) => new stdClass()]],
        ]);
        $stage = new CatchUp(
            DefaultImportSourceFactory::from(ProductWithoutTimestamps::class),
            new Index(self::INDEX),
            new \DateTimeImmutable('2000-01-01 00:00:00')
        );
        $stage->handle($this->elasticsearch);

        $this->assertSame(0, $this->indexedCount());
    }

    /**
     * One product, last changed in 2000, already written by a range job
     * into an index that has the import alias.
     */
    private function importedProduct(): Product
    {
        /** @var Product $product */
        $product = Product::withoutEvents(function () {
            return factory(Product::class)->create(['updated_at' => '2000-01-01 00:00:00']);
        });
        $this->elasticsearch->indices()->create([
            'index' => self::INDEX,
            'body' => ['aliases' => [ImportAlias::of(self::INDEX) => new stdClass()]],
        ]);
        (new ImportRange(
            DefaultImportSourceFactory::from(Product::class),
            Range::between((int) $product->getKey(), null),
            'id',
            ImportAlias::of(self::INDEX),
            3
        ))->handle($this->elasticsearch);
        $this->assertSame(1, $this->indexedCount(), 'precondition: the range job imported the product');

        return $product;
    }

    private function indexedCount(): int
    {
        $this->elasticsearch->indices()->refresh(['index' => self::INDEX]);
        $response = $this->elasticsearch->search([
            'index' => self::INDEX,
            'body' => ['query' => ['match_all' => new stdClass()]],
        ]);

        return (int) $response['hits']['total']['value'];
    }
}
