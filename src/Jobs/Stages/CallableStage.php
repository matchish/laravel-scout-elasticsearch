<?php
declare(strict_types=1);


namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearchServiceProvider;
use Spatie\Async\Task;

/**
 * @internal
 */
final class CallableStage extends Task
{
    /** @var object $stage */
    private $stage;
    /** @var Client $elasticsearch */
    private $elasticsearch;

    /**
     * CallableStage constructor.
     * @param object $stage
     */
    public function __construct(object $stage)
    {
        $this->stage = $stage;
    }

    public function configure(): void
    {
        (new ElasticSearchServiceProvider(app()))->register();
        $this->elasticsearch = app(Client::class);
    }

    public function run(): void
    {
        $this->stage->handle($this->elasticsearch);
    }
}
