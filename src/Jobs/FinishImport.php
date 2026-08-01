<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Matchish\ScoutElasticSearch\Jobs\Stages\StageInterface;

/**
 * Runs the tail of a parallel import after every ImportRange job of
 * the batch has finished. The stages it runs are composed in
 * ImportStages, next to the rest of the pipeline.
 *
 * @internal
 */
final class FinishImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var array<StageInterface>
     */
    private $stages;

    /**
     * @param  array<StageInterface>  $stages
     */
    public function __construct(array $stages)
    {
        $this->stages = $stages;
    }

    public function handle(Client $elasticsearch): void
    {
        foreach ($this->stages as $stage) {
            $stage->handle($elasticsearch);
        }
    }
}
