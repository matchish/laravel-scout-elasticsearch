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
 * Runs the tail of a parallel import once every range is finished. The
 * range job that records the last range starts it; for an import with
 * no rows, the import starts it at once. The stages it runs are
 * composed in ImportStages, next to the rest of the pipeline.
 *
 * @internal
 */
final class FinishImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Running the stages again is safe: catch-up, refresh and the alias
     * switch all reach the same end state.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * @var array<StageInterface>
     */
    private $stages;

    /**
     * @var ImportState|null
     */
    private $state;

    /**
     * @var string|null
     */
    private $batchId;

    /**
     * @param  array<StageInterface>  $stages
     */
    public function __construct(array $stages)
    {
        $this->stages = $stages;
    }

    /**
     * A copy that finishes the import with this state: it publishes only
     * once every range is recorded, and removes the state when done.
     */
    public function forImport(ImportState $state): self
    {
        $copy = clone $this;
        $copy->state = $state;

        return $copy;
    }

    /**
     * A copy that stands down if this batch of range jobs is cancelled.
     */
    public function forBatch(?string $batchId): self
    {
        $copy = clone $this;
        $copy->batchId = $batchId;

        return $copy;
    }

    public function handle(Client $elasticsearch): void
    {
        // A cancelled batch means a newer import replaces this one.
        if ($this->batchId !== null) {
            $batch = Bus::findBatch($this->batchId);
            if ($batch !== null && $batch->cancelled()) {
                return;
            }
        }

        if ($this->state !== null) {
            // The state is removed when the import is published, or when a
            // newer import replaces this one: either way, nothing to finish.
            if (! $this->state->exists($elasticsearch)) {
                return;
            }
            // Only the job that recorded the last range starts this one, so
            // a missing range here is a bug. Never publish an index with
            // missing rows.
            if (! $this->state->complete($elasticsearch)) {
                $this->state->recordFailure($elasticsearch, 'the import was told to finish before every range was imported');

                return;
            }
        }

        foreach ($this->stages as $stage) {
            $stage->handle($elasticsearch);
        }

        // The last step: a console that follows the import treats the
        // state being gone as the import being published.
        if ($this->state !== null) {
            $this->state->delete($elasticsearch);
        }
    }

    /**
     * Called by the queue when the last try failed. The search alias was
     * not switched; the state tells a console that follows the import.
     */
    public function failed(\Throwable $exception): void
    {
        if ($this->state === null) {
            return;
        }

        try {
            $this->state->recordFailure(app(Client::class), $exception->getMessage());
        } catch (\Throwable $e) {
            // The failed job is in the failed_jobs table either way.
            report($e);
        }
    }
}
