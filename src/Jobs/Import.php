<?php

namespace Matchish\ScoutElasticSearch\Jobs;

use Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\ProgressReportable;

/**
 * @internal
 */
final class Import
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
