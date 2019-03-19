<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 09.03.19
 * Time: 13:55
 */

namespace Matchish\ScoutElasticSearch\Jobs;


use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
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
