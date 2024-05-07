<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

interface HitsIteratorAggregate extends \IteratorAggregate
{
    /**
     * @param array $results
     * @param callable|null $callback
     */
    public function __construct(array $results, callable $callback = null);

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable;
}
