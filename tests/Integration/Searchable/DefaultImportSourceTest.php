<?php

namespace Tests\Integration\Searchable;

use App\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSource;
use Tests\TestCase;

class DefaultImportSourceTest extends TestCase
{
    public function test_new_query_has_injected_scopes()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphonePromoUsedAmount = rand(1, 5);
        $iphonePromoNewAmount = rand(6, 10);

        factory(Product::class, $iphonePromoUsedAmount)->states(['iphone', 'promo', 'used'])->create();
        factory(Product::class, $iphonePromoNewAmount)->states(['iphone', 'promo', 'new'])->create();

        Product::setEventDispatcher($dispatcher);
        $source = new DefaultImportSource(Product::class, [new UsedScope()]);
        $products = $source->get();
        $this->assertEquals($iphonePromoUsedAmount, $products->count());
    }

    public function test_chunked_with_complex_scope()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        factory(Product::class, 3)->states(['iphone', 'promo', 'used'])->create();
        factory(Product::class, 2)->states(['kindle', 'promo', 'used'])->create();

        Product::setEventDispatcher($dispatcher);

        $source = new DefaultImportSource(Product::class, [new ComplexScopeWithGroupByAndHaving()]);
        $results = $source->chunked();

        $this->assertEquals(5, $results->sum(fn ($chunk) => $chunk->get()->count()));
    }
}

class UsedScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->where('type', 'used');
    }
}

class ComplexScopeWithGroupByAndHaving implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // Create a subquery to get counts per type
        $subquery = $model->newQuery()
            ->select('type')
            ->selectRaw('COUNT(*) as type_count')
            ->groupBy('type');

        // Join with the subquery and apply conditions
        $builder->select('products.*')
                ->joinSub($subquery, 'type_counts', function($join) {
                    $join->on('products.type', '=', 'type_counts.type');
                })
                ->groupBy('type_counts.type')  // Group only by type, not by id
                ->having('type_counts.type_count', '>', 0);
    }
}
