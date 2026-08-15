<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
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
     * @var string|null
     */
    private $batchId;

    /**
     * @param  array<StageInterface>  $stages
     */
    public function __construct(array $stages, ?string $batchId = null)
    {
        $this->stages = $stages;
        $this->batchId = $batchId;
    }

    /**
     * A copy tied to the batch it finishes.
     */
    public function forBatch(string $batchId): self
    {
        return new self($this->stages, $batchId);
    }

    public function handle(Client $elasticsearch): void
    {
        // Laravel records a skipped job of a cancelled batch as a success,
        // so the batch still reaches zero pending jobs and fires this
        // callback. Publishing here would switch the alias to an index
        // that was abandoned half-built.
        if ($this->batchId !== null) {
            $batch = Bus::findBatch($this->batchId);
            if ($batch !== null && $batch->cancelled()) {
                return;
            }
        }

        foreach ($this->stages as $stage) {
            $stage->handle($elasticsearch);
        }
    }
}
