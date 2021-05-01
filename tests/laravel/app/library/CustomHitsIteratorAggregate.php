<?php

namespace App\Library;

use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;

class CustomHitsIteratorAggregate implements HitsIteratorAggregate
{
    private $hits;

    private $callback;

    public function __construct(array $results, callable $callback = null)
    {
        $this->results = $results;

        $this->callback = $callback;
    }

    public function getIterator()
    {
        $hits = ['test1', 'test2', 'test3'];

        return new \ArrayIterator($hits);
    }
}
