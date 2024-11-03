<?php

declare(strict_types=1);

namespace Tests\Unit\ElasticSearch;

use App\Product;
use Laravel\Scout\Builder;
use Matchish\ScoutElasticSearch\ElasticSearch\SearchFactory;
use Tests\TestCase;

class SearchFactoryTest extends TestCase
{
    public function test_limit_set_in_builder(): void
    {
        $builder = new Builder(new Product(), '*');
        $builder->take($size = 50);

        $search = SearchFactory::create($builder);

        $this->assertEquals($search->getSize(), $size);
    }

    public function test_limit_compatible_with_pagination(): void
    {
        $builder = new Builder(new Product(), '*');
        $builder->take(30);

        $search = SearchFactory::create($builder, [
            'from' => 0,
            'size' => $size = 50,
        ]);

        $this->assertEquals($search->getSize(), $size);
    }
}
