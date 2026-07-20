<?php

declare(strict_types=1);

namespace Tests\Integration\Searchable;

use App\BookWithCustomKey;
use App\Product;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Matchish\ScoutElasticSearch\Searchable\Partitionable;
use Matchish\ScoutElasticSearch\Searchable\Range;
use Matchish\ScoutElasticSearch\Searchable\RangePlan;
use Matchish\ScoutElasticSearch\Searchable\RangePlanner;
use Tests\Fixtures\ProductWithPartitionKey;
use Tests\Fixtures\ProductWithStringPartitionKey;
use Tests\IntegrationTestCase;

final class RangePlannerTest extends IntegrationTestCase
{
    public function test_plans_contiguous_ranges_for_integer_primary_key(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        factory(Product::class, 20)->create();
        Product::setEventDispatcher($dispatcher);

        $plan = RangePlanner::plan($this->source(Product::class), 3, 2);

        $this->assertSame('id', $plan->column());
        $this->assertCount(4, $plan->ranges());
        $this->assertSame((int) Product::query()->min('id'), $plan->ranges()[0]->from());

        $previous = null;
        foreach ($plan->ranges() as $range) {
            if ($previous !== null) {
                $this->assertSame($previous->to(), $range->from());
            }
            $previous = $range;
        }
        $this->assertNull($previous->to());

        $this->assertSame(20, $this->coveredRows(Product::class, $plan));
    }

    public function test_empty_source_produces_empty_plan(): void
    {
        $plan = RangePlanner::plan($this->source(Product::class), 3, 2);

        $this->assertTrue($plan->isEmpty());
    }

    public function test_non_integer_primary_key_without_partition_key_aborts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/BookWithCustomKey.*searchablePartitionKey/s');

        RangePlanner::plan($this->source(BookWithCustomKey::class), 3, 2);
    }

    public function test_declared_partition_key_wins_and_null_values_get_a_bucket(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        factory(Product::class, 6)->create(['weight' => 100]);
        factory(Product::class, 6)->create(['weight' => 500]);
        factory(Product::class, 4)->create(['weight' => null]);
        Product::setEventDispatcher($dispatcher);

        $plan = RangePlanner::plan($this->source(ProductWithPartitionKey::class), 3, 2);

        $this->assertSame('weight', $plan->column());
        $ranges = $plan->ranges();
        $last = end($ranges);
        $this->assertInstanceOf(Range::class, $last);
        $this->assertTrue($last->isNullBucket());
        $this->assertSame(16, $this->coveredRows(ProductWithPartitionKey::class, $plan));
    }

    public function test_non_numeric_partition_column_aborts(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        factory(Product::class, 3)->create();
        Product::setEventDispatcher($dispatcher);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/numeric.*title/s');

        RangePlanner::plan($this->source(ProductWithStringPartitionKey::class), 3, 2);
    }

    private function source(string $class): Partitionable
    {
        $source = DefaultImportSourceFactory::from($class);
        $this->assertInstanceOf(Partitionable::class, $source);

        return $source;
    }

    /**
     * Every row must fall into exactly one range: a gap makes the
     * sum come up short, an overlap makes it overshoot.
     */
    private function coveredRows(string $class, RangePlan $plan): int
    {
        $covered = 0;
        foreach ($plan->ranges() as $range) {
            $query = $class::query();
            if ($range->isNullBucket()) {
                $query->whereNull($plan->column());
            } else {
                $query->where($plan->column(), '>=', $range->from());
                if ($range->to() !== null) {
                    $query->where($plan->column(), '<', $range->to());
                }
            }
            $covered += $query->count();
        }

        return $covered;
    }
}
