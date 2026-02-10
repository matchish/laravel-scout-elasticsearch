<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

/**
 * @extends \IteratorAggregate<int, mixed>
 */
interface HitsIteratorAggregate extends \IteratorAggregate
{
    /**
     * @param  array<mixed>  $results
     * @param  callable|null  $callback
     */
    public function __construct(array $results, ?callable $callback = null);

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable;
}
