<?php

use App\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Matchish\ScoutElasticSearch\Exceptions\ValidationException;
use Tests\IntegrationTestCase;

final class SearchFiltersTest extends IntegrationTestCase
{
    /** @test */
    public function it_filters_search_query_using_greater_than_operators()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphoneCheapAmount = rand(1, 5);
        $iphonePromoAmount = rand(6, 10);

        factory(Product::class, $iphoneCheapAmount)->states(['iphone', 'cheap'])->create();
        factory(Product::class, $iphonePromoAmount)->states(['iphone', 'promo'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphonePromo = Product::search('iphone')
            ->where('price', '>', 80)
            ->get();

        $this->assertEquals($iphonePromo->count(), $iphonePromoAmount);
        $this->assertInstanceOf(Product::class, $iphonePromo->first());
    }

    /** @test */
    public function it_filters_search_query_using_the_equal_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphoneCheapAmount = rand(1, 5);
        $iphonePromoAmount = rand(6, 10);

        factory(Product::class, $iphoneCheapAmount)->states(['iphone', 'cheap'])->create();
        factory(Product::class, $iphonePromoAmount)->states(['iphone', 'promo'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphonePromo = Product::search('iphone')
            ->where('price', '=', 100)
            ->get();

        $this->assertEquals($iphonePromo->count(), $iphonePromoAmount);
        $this->assertInstanceOf(Product::class, $iphonePromo->first());
    }

    /** @test */
    public function it_filters_search_query_using_greater_than_or_equal_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphoneProAmount = rand(1, 5);
        $iphonePromoAmount = rand(6, 10);

        factory(Product::class, $iphoneProAmount)->states(['iphonePro'])->create();
        factory(Product::class, $iphonePromoAmount)->states(['iphone', 'promo'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphonePro = Product::search('iphone')
            ->where('price', '>=', 200)
            ->get();

        $this->assertEquals($iphonePro->count(), $iphoneProAmount);
        $this->assertInstanceOf(Product::class, $iphonePro->first());
    }

    /** @test */
    public function it_filters_search_query_using_less_than_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphoneCheapAmount = rand(1, 5);
        $iphonePromoAmount = rand(6, 10);

        factory(Product::class, $iphoneCheapAmount)->states(['iphone', 'cheap'])->create();
        factory(Product::class, $iphonePromoAmount)->states(['iphone', 'promo'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphoneCheap = Product::search('iphone')
            ->where('price', '<', 80)
            ->get();

        $this->assertEquals($iphoneCheap->count(), $iphoneCheapAmount);
        $this->assertInstanceOf(Product::class, $iphoneCheap->first());
    }

    /** @test */
    public function it_filters_search_query_using_less_or_equal_than_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphoneCheapAmount = rand(1, 5);
        $iphonePromoAmount = rand(6, 10);

        factory(Product::class, $iphoneCheapAmount)->states(['iphone', 'cheap'])->create();
        factory(Product::class, $iphonePromoAmount)->states(['iphone', 'promo'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphoneCheap = Product::search('iphone')
            ->where('price', '<=', 70)
            ->get();

        $this->assertEquals($iphoneCheap->count(), $iphoneCheapAmount);
        $this->assertInstanceOf(Product::class, $iphoneCheap->first());
    }

    /** @test */
    public function it_filters_search_query_using_the_not_equal_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphoneCheapAmount = rand(1, 5);
        $iphonePromoAmount = rand(6, 10);

        factory(Product::class, $iphoneCheapAmount)->states(['iphone', 'cheap'])->create();
        factory(Product::class, $iphonePromoAmount)->states(['iphone', 'promo'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphoneCheap = Product::search('iphone')
            ->where('price', '!=', 100)
            ->get();

        $this->assertEquals($iphoneCheap->count(), $iphoneCheapAmount);
        $this->assertInstanceOf(Product::class, $iphoneCheap->first());
    }

    /** @test */
    public function it_filters_search_query_using_the_between_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphoneCheapAmount = rand(1, 5);
        $iphonePromoAmount = rand(6, 10);

        factory(Product::class, $iphoneCheapAmount)->states(['iphone', 'cheap'])->create();
        factory(Product::class, $iphonePromoAmount)->states(['iphone', 'promo'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphoneCheap = Product::search('iphone')
            ->whereBetween('price', [30, 70])
            ->get();

        $this->assertEquals($iphoneCheap->count(), $iphoneCheapAmount);
        $this->assertInstanceOf(Product::class, $iphoneCheap->first());
    }

    /** @test */
    public function it_filters_search_query_using_the_between_opeator_on_dates()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphonePromoUsedAmount = rand(1, 5);
        $iphonePromoNewAmount = rand(6, 10);
        $iphonePromoLikeNewAmount = rand(1, 5);
        $iphonePromoNewAndLikeNewAmount = $iphonePromoNewAmount + $iphonePromoLikeNewAmount;

        Carbon::setTestNow(now()->subMonth());
        factory(Product::class, $iphonePromoUsedAmount)->states(['iphone'])->create();
        Carbon::setTestNow();
        Carbon::setTestNow(now()->subWeek());
        factory(Product::class, $iphonePromoNewAmount)->states(['iphone'])->create();
        Carbon::setTestNow();
        factory(Product::class, $iphonePromoLikeNewAmount)->states(['iphone'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphonePromoNewAndLikeNew = Product::search('iphone')
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()])
            ->paginate(20);

        $this->assertEquals($iphonePromoNewAndLikeNew->count(), $iphonePromoNewAndLikeNewAmount);
        $this->assertInstanceOf(Product::class, $iphonePromoNewAndLikeNew->first());
    }

    /** @test */
    public function it_filters_search_query_using_the_not_between_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphoneCheapAmount = rand(1, 5);
        $iphonePromoAmount = rand(6, 10);

        factory(Product::class, $iphoneCheapAmount)->states(['iphone', 'cheap'])->create();
        factory(Product::class, $iphonePromoAmount)->states(['iphone', 'promo'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphonePromo = Product::search('iphone')
            ->whereNotBetween('price', [30, 70])
            ->get();

        $this->assertEquals($iphonePromo->count(), $iphonePromoAmount);
        $this->assertInstanceOf(Product::class, $iphonePromo->first());
    }

    /** @test */
    public function it_filters_search_query_using_the_exists_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphoneWithoutDescriptionAmount = rand(1, 5);
        $iphoneWithDescriptionAmount = rand(6, 10);

        factory(Product::class, $iphoneWithoutDescriptionAmount)->states(['iphone'])->create();
        factory(Product::class, $iphoneWithDescriptionAmount)->states(['iphone', 'like new'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphoneWithDescription = Product::search('iphone')
            ->whereExists('description')
            ->get();

        $this->assertEquals($iphoneWithDescription->count(), $iphoneWithDescriptionAmount);
        $this->assertInstanceOf(Product::class, $iphoneWithDescription->first());
    }

    /** @test */
    public function it_filters_search_query_using_the_not_exists_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphoneWithoutDescriptionAmount = rand(1, 5);
        $iphoneWithDescriptionAmount = rand(6, 10);

        factory(Product::class, $iphoneWithoutDescriptionAmount)->states(['iphone'])->create();
        factory(Product::class, $iphoneWithDescriptionAmount)->states(['iphone', 'like new'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphoneWithoutDescription = Product::search('iphone')
            ->whereNotExists('description')
            ->get();

        $this->assertEquals($iphoneWithoutDescription->count(), $iphoneWithoutDescriptionAmount);
        $this->assertInstanceOf(Product::class, $iphoneWithoutDescription->first());
    }

    /** @test */
    public function it_filters_search_query_using_the_in_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphonePromoUsedAmount = rand(1, 5);
        $iphonePromoNewAmount = rand(6, 10);
        $iphonePromoLikeNewAmount = rand(1, 5);
        $iphonePromoUsedAndLikeNewAmount = $iphonePromoLikeNewAmount + $iphonePromoUsedAmount;

        factory(Product::class, $iphonePromoUsedAmount)->states(['iphone', 'promo', 'used'])->create();
        factory(Product::class, $iphonePromoNewAmount)->states(['iphone', 'promo', 'new'])->create();
        factory(Product::class, $iphonePromoLikeNewAmount)->states(['iphone', 'promo', 'like new'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphonePromoUsedAndLikeNew = Product::search('iphone')
            ->where('price', '=', 100)
            ->whereIn('type', ['like new', 'used'])
            ->get();

        $this->assertEquals($iphonePromoUsedAndLikeNew->count(), $iphonePromoUsedAndLikeNewAmount);
        $this->assertInstanceOf(Product::class, $iphonePromoUsedAndLikeNew->first());
    }

    /** @test */
    public function it_filters_search_query_using_the_starts_with_operator()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        $iphonePromoUsedAmount = rand(1, 5);
        $iphonePromoNewAmount = rand(6, 10);
        $iphonePromoLikeNewAmount = rand(1, 5);

        factory(Product::class, $iphonePromoUsedAmount)->states(['iphone', 'promo', 'used'])->create();
        factory(Product::class, $iphonePromoNewAmount)->states(['iphone', 'promo', 'new'])->create();
        factory(Product::class, $iphonePromoLikeNewAmount)->states(['iphone', 'promo', 'like new'])->create();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $iphonePromoLikeNew = Product::search('iphone')
            ->where('price', '=', 100)
            ->whereStartsWith('type', "li")
            ->get();

        $this->assertEquals($iphonePromoLikeNew->count(), $iphonePromoLikeNewAmount);
        $this->assertInstanceOf(Product::class, $iphonePromoLikeNew->first());
    }

    /** @test */
    public function it_throws_an_exception_when_the_operator_is_not_supported()
    {
        $dispatcher = Product::getEventDispatcher();
        Product::unsetEventDispatcher();

        Product::setEventDispatcher($dispatcher);

        Artisan::call('scout:import');

        $this->expectException(ValidationException::class);

        Product::search('iphone')
            ->where('price', 'unsupportedOperation', 100)
            ->get();
    }
}