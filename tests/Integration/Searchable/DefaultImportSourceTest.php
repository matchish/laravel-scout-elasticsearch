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

        factory(Product::class, 5)->states(['iphone', 'promo', 'used'])->create();
        factory(Product::class, 10)->states(['iphone', 'promo', 'new'])->create();

        Product::setEventDispatcher($dispatcher);

        $source = new DefaultImportSource(Product::class, [new GroupByTypeScope()]);
        $results = $source->chunked();
        
        $this->assertEquals(15, $results->sum(fn ($chunk) => $chunk->get()->count()));
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

class GroupByTypeScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $builder->groupBy('type', 'id')->havingRaw('COUNT(*) > 0');
    }
}
