<?php

declare(strict_types=1);

namespace Tests\Unit\ElasticSearch;

use App\Product;
use InvalidArgumentException;
use Laravel\Scout\Builder;
use Matchish\ScoutElasticSearch\ElasticSearch\SearchFactory;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
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

    public function test_where_angle_brackets_operator_adds_term_query_to_must_not(): void
    {
        $builder = new Builder(new Product(), '');
        $builder->wheres = [
            ['field' => 'type', 'operator' => '<>', 'value' => 'used'],
        ];

        $query = SearchFactory::create($builder)->toArray()['query'];

        $this->assertEquals([
            'bool' => [
                'must_not' => [
                    ['term' => ['type' => 'used']],
                ],
            ],
        ], $query);
    }

    public function test_where_angle_brackets_operator_adds_query_object_to_must_not(): void
    {
        $priceRange = new RangeQuery('price', [RangeQuery::GTE => 100, RangeQuery::LTE => 200]);
        $builder = new Builder(new Product(), '');
        $builder->wheres = [
            ['field' => 'price', 'operator' => '<>', 'value' => $priceRange],
        ];

        $query = SearchFactory::create($builder)->toArray()['query'];

        $this->assertEquals([
            'bool' => [
                'must_not' => [
                    ['range' => ['price' => ['gte' => 100, 'lte' => 200]]],
                ],
            ],
        ], $query);
    }

    /**
     * @dataProvider unsupportedOperators
     */
    public function test_where_unsupported_operator_throws_exception(string $operator): void
    {
        $builder = new Builder(new Product(), '');
        $builder->wheres = [
            ['field' => 'type', 'operator' => $operator, 'value' => 'used'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Operator [{$operator}] is not supported");

        SearchFactory::create($builder);
    }

    public static function unsupportedOperators(): array
    {
        return [
            'like' => ['like'],
            'not like' => ['not like'],
            'null-safe equals' => ['<=>'],
        ];
    }

    public function test_where_unsupported_operator_with_query_object_adds_query_object_to_filter(): void
    {
        $priceRange = new RangeQuery('price', [RangeQuery::GTE => 100, RangeQuery::LTE => 200]);
        $builder = new Builder(new Product(), '');
        $builder->wheres = [
            ['field' => 'price', 'operator' => 'between', 'value' => $priceRange],
        ];

        $query = SearchFactory::create($builder)->toArray()['query'];

        $this->assertEquals([
            'bool' => [
                'filter' => [
                    ['range' => ['price' => ['gte' => 100, 'lte' => 200]]],
                ],
            ],
        ], $query);
    }

    public function test_where_equals_null_adds_exists_query_to_must_not(): void
    {
        $builder = new Builder(new Product(), '');
        $builder->wheres = [
            ['field' => 'discount', 'operator' => '=', 'value' => null],
        ];

        $query = SearchFactory::create($builder)->toArray()['query'];

        $this->assertEquals([
            'bool' => [
                'must_not' => [
                    ['exists' => ['field' => 'discount']],
                ],
            ],
        ], $query);
    }

    /**
     * @dataProvider notEqualsOperators
     */
    public function test_where_not_equals_null_adds_exists_query_to_filter(string $operator): void
    {
        $builder = new Builder(new Product(), '');
        $builder->wheres = [
            ['field' => 'discount', 'operator' => $operator, 'value' => null],
        ];

        $query = SearchFactory::create($builder)->toArray()['query'];

        $this->assertEquals([
            'bool' => [
                'filter' => [
                    ['exists' => ['field' => 'discount']],
                ],
            ],
        ], $query);
    }

    public static function notEqualsOperators(): array
    {
        return [
            'exclamation mark' => ['!='],
            'angle brackets' => ['<>'],
        ];
    }

    public function test_old_scout_wheres_format_null_adds_exists_query_to_must_not(): void
    {
        $builder = new Builder(new Product(), '');
        $builder->wheres = ['discount' => null];

        $query = SearchFactory::create($builder)->toArray()['query'];

        $this->assertEquals([
            'bool' => [
                'must_not' => [
                    ['exists' => ['field' => 'discount']],
                ],
            ],
        ], $query);
    }
}
