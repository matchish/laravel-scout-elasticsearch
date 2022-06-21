<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Product;
use Illuminate\Support\Facades\Artisan;
use Tests\IntegrationTestCase;

final class IndexCreatingTest extends IntegrationTestCase
{
    public function test_index_creating_without_timestamp(): void
    {
        factory(Product::class, 50)->state('iphone')->create();

        Artisan::call('scout:import');
        $iphone = Product::search('iphone')
            ->where('price', 100)
            ->whereIn('type', ['used', 'like new'])
            ->get();

        $this->assertInstanceOf(Product::class, $iphone->first());
    }
}
