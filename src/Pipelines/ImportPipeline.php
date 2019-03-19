<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 14.03.19
 * Time: 12:10
 */

namespace Matchish\ScoutElasticSearch\Pipelines;

use Elasticsearch\Client;
use League\Pipeline\Pipeline;
use League\Pipeline\ProcessorInterface;
use Matchish\ScoutElasticSearch\Pipelines\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Pipelines\Stages\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Pipelines\Stages\PullFromSource;
use Matchish\ScoutElasticSearch\Pipelines\Stages\RefreshIndex;
use Matchish\ScoutElasticSearch\Pipelines\Stages\SwitchToNewAndRemoveOldIndex;

/**
 * @internal
 */
final class ImportPipeline extends Pipeline
{

    public function __construct(Client $elasticsearch, ProcessorInterface $processor = null, callable ...$stages)
    {
        if (!count($stages)) {
            $stages = [
                new CleanUp($elasticsearch),
                new CreateWriteIndex($elasticsearch),
                new PullFromSource($elasticsearch),
                new RefreshIndex($elasticsearch),
                new SwitchToNewAndRemoveOldIndex($elasticsearch)
            ];
        }
        parent::__construct($processor, ...$stages);
    }

}
