<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs\Stages;

use App\Product;
use Illuminate\Support\Facades\DB;
use Matchish\ScoutElasticSearch\ElasticSearch\ImportAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\Stages\CatchUp;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use stdClass;
use Tests\Fixtures\ProductWithoutTimestamps;
use Tests\IntegrationTestCase;

final class CatchUpTest extends IntegrationTestCase
{
    private const INDEX = 'products_import';

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
