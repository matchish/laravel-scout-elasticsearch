<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 18.03.19
 * Time: 11:23.
 */

namespace Tests\Unit\Pipelines;

use Tests\TestCase;
use Elasticsearch\Client;
use League\Pipeline\ProcessorInterface;
use Matchish\ScoutElasticSearch\Pipelines\ImportPipeline;
use Matchish\ScoutElasticSearch\Pipelines\Stages\CleanUp;
use Matchish\ScoutElasticSearch\Pipelines\Stages\RefreshIndex;
use Matchish\ScoutElasticSearch\Pipelines\Stages\PullFromSource;
use Matchish\ScoutElasticSearch\Pipelines\Stages\CreateWriteIndex;
use Matchish\ScoutElasticSearch\Pipelines\Stages\SwitchToNewAndRemoveOldIndex;

class ImportPipelineTest extends TestCase
{
    public function test_stages()
    {
        $elasticsearch = app(Client::class);
        $processor = new Processor();
        $pipeline = new ImportPipeline($elasticsearch, $processor);
        $pipeline->process(new \stdClass());
        $this->assertEquals(5, count($processor->stages));
        $this->assertInstanceOf(CleanUp::class, $processor->stages[0]);
        $this->assertInstanceOf(CreateWriteIndex::class, $processor->stages[1]);
        $this->assertInstanceOf(PullFromSource::class, $processor->stages[2]);
        $this->assertInstanceOf(RefreshIndex::class, $processor->stages[3]);
        $this->assertInstanceOf(SwitchToNewAndRemoveOldIndex::class, $processor->stages[4]);
    }
}

class Processor implements ProcessorInterface
{
    public $stages;

    /**
     * Process the payload using multiple stages.
     *
     * @param mixed $payload
     *
     * @return mixed
     */
    public function process($payload, callable ...$stages)
    {
        $this->stages = $stages;

        return $payload;
    }
}
