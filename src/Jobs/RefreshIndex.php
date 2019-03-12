<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 09.03.19
 * Time: 13:55
 */

namespace Matchish\ScoutElasticSearch\Jobs;


use Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;

/**
 * @internal
 */
final class RefreshIndex implements ShouldQueue
{
    use Queueable;
    /**
     * @var Index
     */
    private $index;

    /**
     * RefreshIndex constructor.
     * @param Index $index
     */
    public function __construct(Index $index)
    {
        $this->index = $index;
    }

    /**
     * @param Client $elasticsearch
     */
    public function handle(Client $elasticsearch)
    {
        $elasticsearch->indices()->refresh(['index' => $this->index->alias()]);
    }
}
