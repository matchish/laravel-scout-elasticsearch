<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Book;
use App\Product;
use App\Ticket;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\LazyCollection;
use Matchish\ScoutElasticSearch\MixedSearch;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use Tests\IntegrationTestCase;

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
        $iphoneLikeNewAmount = rand(1, 5);
        $iphonePromoUsedAndLikeNewAmount = $iphonePromoLikeNewAmount + $iphonePromoUsedAmount;

        factory(Product::class, $kindleAmount)->states(['kindle', 'cheap'])->create();
        factory(Product::class, $iphoneLuxuryAmount)->states(['iphone', 'luxury'])->create();
        factory(Product::class, $iphoneLikeNewAmount)->states(['iphone', 'like new'])->create();
        factory(Product::class, $iphonePromoUsedAmount)->states(['iphone', 'promo', 'used'])->create();
        factory(Product::class, $iphonePromoNewAmount)->states(['iphone', 'promo', 'new'])->create();
        factory(Product::class, $iphonePromoLikeNewAmount)->states(['iphone', 'promo', 'like new'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphonePromoUsedAndLikeNew = Product::search('iphone')
            ->where('price', 100)
            ->whereIn('type', ['used', 'like new'])
            ->get();

        $this->assertEquals($iphonePromoUsedAndLikeNew->count(), $iphonePromoUsedAndLikeNewAmount);
        $this->assertInstanceOf(Product::class, $iphonePromoUsedAndLikeNew->first());
    }

    public function test_search_with_custom_filter()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $kindleCheapAmount = rand(1, 5);
        $iphoneLuxuryAmount = rand(1, 5);
        $iphonePromoUsedAmount = rand(1, 5);
        $iphonePromoNewAmount = rand(6, 10);
        $iphonePromoLikeNewAmount = rand(1, 5);
        $iphonePromoUsedAndLikeNewAmount = $iphonePromoLikeNewAmount + $iphonePromoUsedAmount;

        factory(Product::class, $kindleCheapAmount)->states(['iphone', 'cheap'])->create();
        factory(Product::class, $iphoneLuxuryAmount)->states(['iphone', 'luxury'])->create();
        factory(Product::class, $iphonePromoUsedAmount)->states(['iphone', 'promo', 'used'])->create();
        factory(Product::class, $iphonePromoNewAmount)->states(['iphone', 'promo', 'new'])->create();
        factory(Product::class, $iphonePromoLikeNewAmount)->states(['iphone', 'promo', 'like new'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        // Promo Product Test
        $iphonePromoUsedAndLikeNewWithRange = Product::search('iphone')
            ->where('price', new RangeQuery('price', [
                RangeQuery::GTE => 100,  // Promo Products
                RangeQuery::LTE => 100,  // Promo Products
            ]))
            ->whereIn('type', ['used', 'like new'])
            ->get();

        $this->assertEquals($iphonePromoUsedAndLikeNewWithRange->count(), $iphonePromoUsedAndLikeNewAmount);
        $this->assertInstanceOf(Product::class, $iphonePromoUsedAndLikeNewWithRange->first(), 'Promo Product Assert');

        // Luxury Product Test
        $iphoneLuxuryUsedAndLikeNewWithRange = Product::search('iphone')
            ->where('price', new RangeQuery('price', [
                RangeQuery::GTE => 1000, // Luxury Products
            ]))
            ->get();

        $this->assertEquals($iphoneLuxuryUsedAndLikeNewWithRange->count(), $iphoneLuxuryAmount, 'Luxury Product Count Assert');
        $this->assertInstanceOf(Product::class, $iphoneLuxuryUsedAndLikeNewWithRange->first());

        // Cheap Product Test
        $iphoneCheapWithRange = Product::search('iphone')
            ->where('price', new RangeQuery('price', [
                RangeQuery::LTE => 70, // Cheap Products
            ]))
            ->get();

        $this->assertEquals($kindleCheapAmount, $iphoneCheapWithRange->count(), 'Cheap Product Count Assert');
        $this->assertInstanceOf(Product::class, $iphoneCheapWithRange->first());
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

    public function test_mixed()
    {
        $newyorkAmount = rand(1, 5);
        $barcelonaAmount = rand(1, 5);

        $dispatcher = Ticket::getEventDispatcher();
        Ticket::unsetEventDispatcher();

        factory(Ticket::class, $newyorkAmount)->state('new-york')->create();
        factory(Ticket::class, $barcelonaAmount)->state('barcelona')->create();

        Ticket::setEventDispatcher($dispatcher);

        $dispatcher = Book::getEventDispatcher();
        Book::unsetEventDispatcher();

        factory(Book::class, $newyorkAmount)->state('new-york')->create();
        factory(Book::class, $barcelonaAmount)->state('barcelona')->create();

        Book::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $mixed = MixedSearch::search('Barcelona')->within(
            implode(',', [
                (new Book)->searchableAs(),
                (new Ticket())->searchableAs(),
            ]))->get();
        $this->assertEquals($barcelonaAmount * 2, $mixed->count());
        $this->assertEquals(['tickets' => $barcelonaAmount, 'books' => $barcelonaAmount], $mixed->map->getTable()->countBy()->all());
    }

    public function test_mixed_no_results()
    {
        Artisan::call('scout:import');

        $mixed = MixedSearch::search('lisbon')->within(
            implode(',', [(new Book)->searchableAs(),
                (new Ticket())->searchableAs(),
            ]))->get();
        $this->assertEquals(0, $mixed->count());
    }

    public function test_mixed_cursor()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Not implemented for MixedSearch');
        Artisan::call('scout:import');

        $mixed = MixedSearch::search('*')->within(
            implode(',', [(new Book)->searchableAs(),
                (new Ticket())->searchableAs(),
            ]))->cursor();
    }

    public function test_cursor()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();
        $kindleAmount = rand(1, 5);
        factory(Product::class, $kindleAmount)->state('kindle')->create();
        Product::setEventDispatcher($dispatcher);
        Artisan::call('scout:import');

        $kindle = Product::search('kindle')
            ->cursor();
        $this->assertEquals(LazyCollection::class, get_class($kindle));
        $this->assertEquals($kindleAmount, $kindle->count());
    }

    public function test_cursor_no_results()
    {
        Artisan::call('scout:import');

        $kindle = Product::search('lisbon')
            ->cursor();
        $this->assertEquals(LazyCollection::class, get_class($kindle));
        $this->assertEquals(0, $kindle->count());
    }

    public function test_empty_query_string()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $greaterCount = rand(10, 100);

        factory(Product::class, $greaterCount)->create(['price' => rand(200, 300)]);

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $notCheapProducts = Product::search()
            ->where('price', new RangeQuery('price', [
                RangeQuery::GTE => 190,
            ]))->paginate(100);

        $cheapProducts = Product::search()
            ->where('price', new RangeQuery('price', [
                RangeQuery::LTE => 190,
            ]))->get();

        $expensiveProducts = Product::search()
            ->where('price', new RangeQuery('price', [
                RangeQuery::GTE => 310,
            ]))->get();

        $this->assertEquals($greaterCount, $notCheapProducts->total());
        $this->assertEquals(0, $cheapProducts->count());
        $this->assertEquals(0, $expensiveProducts->count());
    }
}
