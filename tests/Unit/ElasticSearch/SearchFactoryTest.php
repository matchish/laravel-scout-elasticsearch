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
        $builder->take($expectedSize = 50);

        $search = SearchFactory::create($builder);

        $this->assertEquals($expectedSize, $search->getSize());
    }

    public function test_limit_compatible_with_pagination(): void
    {
        $builder = new Builder(new Product(), '*');
        $builder->take(30);

        $search = SearchFactory::create($builder, [
            'from' => 0,
            'size' => $expectedSize = 50,
        ]);

        $this->assertEquals($expectedSize, $search->getSize());
    }

    public function test_size_set_in_options_dont_take_effect(): void
    {
        $builder = new Builder(new Product(), '*');
        $builder->take($expectedSize = 30)
            ->options([
                'size' => 100,
            ]);

        $search = SearchFactory::create($builder);

        $this->assertEquals($expectedSize, $search->getSize());
    }

    public function test_from_set_in_options_take_effect(): void
    {
        $builder = new Builder(new Product(), '*');
        $builder->options([
            'from' => $expectedFrom = 100,
        ]);

        $search = SearchFactory::create($builder);

        $this->assertEquals($expectedFrom, $search->getFrom());
    }

    public function test_both_parameters_dont_take_effect_on_pagination(): void
    {
        $builder = new Builder(new Product(), '*');
        $builder->options([
            'from' => 250,
        ])
            ->take(30);

        $search = SearchFactory::create($builder, [
            'from' => $expectedFrom = 100,
            'size' => $expectedSize = 50,
        ]);

        $this->assertEquals($expectedSize, $search->getSize());
        $this->assertEquals($expectedFrom, $search->getFrom());
    }

    public function test_source_can_be_set_from_options(): void
    {
        $builder = new Builder(new Product(), '*');
        $builder->options([
            'source' => $expectedFields = ['title', 'price'],
        ]);

        $search = SearchFactory::create($builder);

        $this->assertEquals($expectedFields, $search->isSource());
    }
}
