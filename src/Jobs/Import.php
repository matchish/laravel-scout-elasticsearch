<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;

/**
 * @internal
 */
final class Import implements ShouldQueue
{
    use Queueable;

    /**
     * @var string
     */
    private $searchable;

    /**
     * @param string $searchable
     */
    public function __construct(string $searchable)
    {
        $this->searchable = $searchable;
    }

    /**
     * @param ElasticSearchEngine $engine
     */
    public function handle(ElasticSearchEngine $engine): void
    {
        $engine->sync(new $this->searchable);
    }
}
