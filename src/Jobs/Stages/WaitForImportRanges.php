<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Bus;

/**
 * Follows the batch of range jobs and reports how many have finished,
 * so the console shows real progress instead of stopping at "queued".
 *
 * The import loop calls handle() again while completed() is false, so
 * each call polls once and then waits. Only the console process runs
 * this stage: a queue worker that waited here could occupy the very
 * worker slot the range jobs need.
 *
 * @internal
 */
final class WaitForImportRanges implements StageInterface
{
    const POLL_INTERVAL_MICROSECONDS = 1000000;

    /**
     * @var DispatchImportRanges
     */
    private $dispatch;

    /**
     * @var int
     */
    private $finishedJobs = 0;

    /**
     * @var int
     */
    private $advanceBy = 0;

    /**
     * @var bool
     */
    private $done = false;

    public function __construct(DispatchImportRanges $dispatch)
    {
        $this->dispatch = $dispatch;
    }

    public function handle(?Client $elasticsearch = null): void
    {
        $batchId = $this->dispatch->batchId();
        if ($batchId === null) {
            $this->done = true;

            return;
        }

        $batch = Bus::findBatch($batchId);
        if ($batch === null) {
            $this->done = true;

            return;
        }

        $finished = $batch->totalJobs - $batch->pendingJobs;
        $this->advanceBy = max(0, $finished - $this->finishedJobs);
        $this->finishedJobs = $finished;

        if ($batch->finished() || $batch->cancelled()) {
            $this->done = true;

            if ($batch->failedJobs > 0) {
                throw new \Exception(sprintf(
                    '%d of %d range jobs failed, so the search alias was not switched. The old index still serves searches; check the failed_jobs table for the cause.',
                    $batch->failedJobs,
                    $batch->totalJobs
                ));
            }

            if ($batch->cancelled()) {
                throw new \Exception(
                    'This import was cancelled before it finished, most likely because a newer import for the same index started. The search alias was not changed.'
                );
            }

            return;
        }

        usleep(self::POLL_INTERVAL_MICROSECONDS);
    }

    public function title(): string
    {
        return 'Indexing...';
    }

    public function estimate(): int
    {
        return count($this->dispatch->plan()->ranges());
    }

    public function advance(): int
    {
        $advance = $this->advanceBy;
        $this->advanceBy = 0;

        return $advance;
    }

    public function completed(): bool
    {
        return $this->done;
    }
}
