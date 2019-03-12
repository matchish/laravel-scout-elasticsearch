<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 06.03.19
 * Time: 14:51
 */

namespace Matchish\ScoutElasticSearch\Jobs;


use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;

/**
 * @internal
 */
final class SwitchToNewAndRemoveOldIndex implements ShouldQueue
{
    use Queueable;
    /**
     * @var Index
     */
    private $index;

    /**
     * SwapIndices constructor.
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
        try {
            $response = $elasticsearch->indices()->getAliases(['index' => '*', 'name' => $this->index->alias()]);
        } catch (Missing404Exception $e) {
            $response = [];
        }

        $actions = [];
        foreach ($response as $index => $alias) {
            if ($index != $this->index->name()) {
                $actions[] = ['remove_index' => ['index' => $index]];
            } else {
                $actions[] = ['add' => ['index' => $index, 'alias' => $this->index->alias()]];
            }
        }
        $elasticsearch->indices()->updateAliases([
            'body' => [
                'actions' => $actions
            ]
        ]);
    }
}
