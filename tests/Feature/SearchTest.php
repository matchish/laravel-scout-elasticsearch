<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Product;
use Illuminate\Pagination\Paginator;
use Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Artisan;

final class SearchTest extends IntegrationTestCase
{
    public function test_search_with_filters(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $kindleAmount = rand(1, 5);
        $iphoneLuxuryAmount = rand(1, 5);
        $iphonePromoUsedAmount = rand(1, 5);
        $iphonePromoNewAmount = rand(6, 10);
        $iphonePromoLikeNewAmount = rand(1, 5);

        factory(Product::class, $kindleAmount)->states(['kindle', 'cheap'])->create();
        factory(Product::class, $iphoneLuxuryAmount)->states(['iphone', 'luxury'])->create();
        factory(Product::class, $iphonePromoUsedAmount)->states(['iphone', 'promo', 'used'])->create();
        factory(Product::class, $iphonePromoNewAmount)->states(['iphone', 'promo', 'new'])->create();
        factory(Product::class, $iphonePromoLikeNewAmount)->states(['iphone', 'promo', 'like new'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphonePromoNew = Product::search('iphone')
            ->where('price', "100")
            ->where('type', 'new')
            ->get();
        $this->assertEquals($iphonePromoNewAmount, $iphonePromoNew->count());
        $this->assertInstanceOf(Product::class, $iphonePromoNew->first());
    }

    public function test_sorted_paginate(): void
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $kindleAmount = rand(1, 5);

        factory(Product::class, $kindleAmount)->state('kindle')->create();
        collect([32, 15, 14, 45, 22, 23, 4, 8])->each(function ($price) {
            factory(Product::class, 1)->state('iphone')->create(['price' => $price]);
        });

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        Paginator::currentPageResolver(function () {
            return 3;
        });

        $iphones = Product::search('iphone')
            ->orderBy('price', 'ASC')
            ->paginate(3);
        $this->assertEquals(8, $iphones->total());
        $this->assertEquals(2, $iphones->count());
        $this->assertEquals([32, 45], $iphones->getCollection()->map->price->all());
    }

    public function test_within()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $kindleAmount = rand(1, 5);

        factory(Product::class, $kindleAmount)->state('kindle')->create();

        Product::setEventDispatcher($dispatcher);

        $this->app['config']['scout.prefix'] = 'new_';
        Artisan::call('scout:import');
        $this->app['config']['scout.prefix'] = null;

        $kindle = Product::search('kindle')
            ->within('new_products')
            ->get();
        $this->assertEquals($kindleAmount, $kindle->count());
    }

}
