<?php

namespace Matchish\ScoutElasticSearch;

use App\Library\CustomHitsIteratorAggregate;
use Matchish\ScoutElasticSearch\ElasticSearch\EloquentHitsIteratorAggregate;
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;
use Tests\TestCase;

class HitsIteratorAggregateTest extends TestCase
{
    public function test_hits_iterator_aggregate_binds_to_eloquent_implementation()
    {
        $iteratorAggregate = $this->app->makeWith(HitsIteratorAggregate::class, [
            'results' => [],
            'callback' => null,
        ]);

        $this->assertEquals(EloquentHitsIteratorAggregate::class, get_class($iteratorAggregate));
    }

    public function test_override_bind_for_custom_iterator_aggregate_implementation()
    {
        $this->app->bind(HitsIteratorAggregate::class, CustomHitsIteratorAggregate::class);

        $iteratorAggregate = $this->app->makeWith(HitsIteratorAggregate::class, [
            'results' => [],
            'callback' => null,
        ]);

        $this->assertEquals(CustomHitsIteratorAggregate::class, get_class($iteratorAggregate));
    }
}
