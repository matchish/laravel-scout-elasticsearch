<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @extends Arrayable<int, mixed>
 * @extends \IteratorAggregate<int, mixed>
 */
interface SearchResults extends Arrayable, \IteratorAggregate
{
}
