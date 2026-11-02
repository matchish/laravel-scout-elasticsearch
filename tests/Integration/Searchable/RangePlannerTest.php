<?php

declare(strict_types=1);

namespace Tests\Integration\Searchable;

use App\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;
use Matchish\ScoutElasticSearch\Searchable\Partitionable;
use Matchish\ScoutElasticSearch\Searchable\Range;
use Matchish\ScoutElasticSearch\Searchable\RangePlan;
use Matchish\ScoutElasticSearch\Searchable\RangePlanner;
use Tests\Fixtures\BookWithoutPartitionKey;
use Tests\Fixtures\ProductWithBigIntegerKey;
use Tests\Fixtures\ProductWithPartitionKey;
use Tests\Fixtures\ProductWithStringPartitionKey;
use Tests\IntegrationTestCase;

final class RangePlannerTest extends IntegrationTestCase
{
    public function test_covers_keys_too_large_for_a_float(): void
    {
        // A float cannot hold every integer above 2^53: 2^53 + 3 becomes
        // 2^53 + 4. The usual products table has a 32-bit key, so these
        // keys need a table of their own.
        Schema::create('big_integer_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->softDeletes();
        });

        try {
            DB::table('big_integer_products')->insert([
                ['id' => 9007199254740995],
                ['id' => 9007199254741995],
                ['id' => 9007199254742995],
            ]);

            $plan = RangePlanner::plan($this->source(ProductWithBigIntegerKey::class), 1, 1);

            $this->assertGreaterThan(1, count($plan->ranges()), 'precondition: the keys are split into ranges');
            $this->assertSame(3, $this->coveredRows(ProductWithBigIntegerKey::class, $plan));
        } finally {
            Schema::dropIfExists('big_integer_products');
        }
    }

    public function test_covers_keys_at_both_ends_of_the_signed_integer_range(): void
    {
        // The widest span a signed 64-bit key can have.
        Schema::create('big_integer_products', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->softDeletes();
        });

        try {
            DB::table('big_integer_products')->insert([
                ['id' => -9223372036854775000],
                ['id' => 0],
                ['id' => 9223372036854775000],
            ]);

            $plan = RangePlanner::plan($this->source(ProductWithBigIntegerKey::class), 1, 1);

            $this->assertGreaterThan(1, count($plan->ranges()), 'precondition: the keys are split into ranges');
            $this->assertSame(3, $this->coveredRows(ProductWithBigIntegerKey::class, $plan));
        } finally {
            Schema::dropIfExists('big_integer_products');
        }
    }

    public function test_covers_a_row_added_below_the_planned_minimum(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        factory(Product::class, 6)->create(['weight' => 100]);
        factory(Product::class, 6)->create(['weight' => 500]);
        Product::setEventDispatcher($dispatcher);

        $plan = RangePlanner::plan($this->source(ProductWithPartitionKey::class), 3, 2);

        // Inserted after planning, with a partition value below anything
        // the planner saw.
        Product::withoutEvents(function () {
            factory(Product::class)->create(['weight' => 5]);
        });

        $this->assertSame(13, $this->coveredRows(ProductWithPartitionKey::class, $plan));
    }

    public function test_plans_contiguous_ranges_for_integer_primary_key(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        factory(Product::class, 20)->create();
        Product::setEventDispatcher($dispatcher);

        $plan = RangePlanner::plan($this->source(Product::class), 3, 2);

        $this->assertSame('id', $plan->column());
        $this->assertCount(4, $plan->ranges());
        // Both outer ends are open, so the plan covers every key by
        // construction; min and max only place the inner boundaries.
        $this->assertNull($plan->ranges()[0]->from());

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
        $this->expectExceptionMessageMatches('/BookWithoutPartitionKey.*searchablePartitionKey/s');

        RangePlanner::plan($this->source(BookWithoutPartitionKey::class), 3, 2);
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
                // Mirrors ImportRange: NULL values belong to the null bucket.
                $query->whereNotNull($plan->column());
                if ($range->from() !== null) {
                    $query->where($plan->column(), '>=', $range->from());
                }
                if ($range->to() !== null) {
                    $query->where($plan->column(), '<', $range->to());
                }
            }
            $covered += $query->count();
        }

        return $covered;
    }
}
