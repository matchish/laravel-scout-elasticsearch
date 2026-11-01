<?php

namespace Tests\Unit\ElasticSearch\Params;

use App\Product;
use Illuminate\Support\Carbon;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
use Tests\TestCase;

class BulkTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_delete()
    {
        Carbon::setTestNow('2020-01-01 00:00:00');
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->delete($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [['delete' => [
                '_index' => 'products',
                '_id' => 2,
                'routing' => 2,
                // A live delete becomes true as it is sent, so it is
                // versioned by the current time, with or without updated_at.
                'version' => Carbon::now()->getTimestamp() * 3 + 2,
                'version_type' => 'external_gte',
            ]]],
        ], $params);
    }

    public function test_delete_with_custom_key_name()
    {
        Carbon::setTestNow('2020-01-01 00:00:00');
        $this->app['config']['scout.key'] = 'title';
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->delete($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [['delete' => [
                '_index' => 'products',
                '_id' => 'Scout',
                'routing' => 'Scout',
                'version' => Carbon::now()->getTimestamp() * 3 + 2,
                'version_type' => 'external_gte',
            ]]],
        ], $params);
    }

    public function test_versions_rank_writers_by_how_fresh_their_data_is(): void
    {
        Carbon::setTestNow('2020-01-02 00:00:00');
        $now = Carbon::now()->getTimestamp();
        $changed = Carbon::parse('2020-01-01 00:00:00')->getTimestamp();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $product->updated_at = Carbon::createFromTimestamp($changed);

        $this->assertSame([$changed * 3, 'external'], $this->versionOf(Bulk::WRITER_SNAPSHOT, 'index', $product));
        $this->assertSame([$changed * 3 + 1, 'external'], $this->versionOf(Bulk::WRITER_CATCH_UP, 'index', $product));
        $this->assertSame([$changed * 3 + 2, 'external_gte'], $this->versionOf(Bulk::WRITER_LIVE, 'index', $product));
        // A delete that is not live records the row's last change.
        $this->assertSame([$changed * 3 + 1, 'external'], $this->versionOf(Bulk::WRITER_CATCH_UP, 'delete', $product));
        // A live delete records the moment it is sent.
        $this->assertSame([$now * 3 + 2, 'external_gte'], $this->versionOf(Bulk::WRITER_LIVE, 'delete', $product));
    }

    public function test_a_timestamp_ahead_of_the_clock_is_capped_at_now(): void
    {
        Carbon::setTestNow('2020-01-01 00:00:00');
        $now = Carbon::now()->getTimestamp();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $product->updated_at = Carbon::createFromTimestamp($now + 3600);

        $this->assertSame([$now * 3, 'external'], $this->versionOf(Bulk::WRITER_SNAPSHOT, 'index', $product));
    }

    public function test_unknown_writer_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Bulk(null, false, 'someone');
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function versionOf(string $writer, string $operation, Product $product): array
    {
        $bulk = new Bulk(null, false, $writer);
        $bulk->{$operation}($product);
        $action = $bulk->toArray()['body'][0][$operation];

        return [$action['version'], $action['version_type']];
    }

    public function test_index()
    {
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->index($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [
                ['index' => ['_index' => 'products', '_id' => 2, 'routing' => 2]],
                ['title' => 'Scout', 'id' => 2, '__class_name' => 'App\Product'],
            ],
        ], $params);
    }

    public function test_index_with_custom_key_name()
    {
        $this->app['config']['scout.key'] = 'title';
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->index($product);
        $params = $bulk->toArray();

        $this->assertEquals([
            'body' => [
                ['index' => ['_index' => 'products', '_id' => 'Scout', 'routing' => 'Scout']],
                ['title' => 'Scout', 'id' => 2, '__class_name' => 'App\Product'],
            ],
        ], $params);
    }

    public function test_push_soft_delete_meta_data()
    {
        $this->app['config']['scout.soft_delete'] = true;
        $bulk = new Bulk();
        $product = new Product(['title' => 'Scout']);
        $product->id = 2;
        $bulk->index($product);
        $params = $bulk->toArray();
        $this->assertEquals([
            'body' => [
                ['index' => ['_index' => 'products', '_id' => 2, 'routing' => 2]],
                ['title' => 'Scout', '__soft_deleted' => 0, 'id' => 2, '__class_name' => 'App\Product'],
            ],
        ], $params);
    }
}
