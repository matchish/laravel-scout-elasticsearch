<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\Jobs\ImportBatches;

/**
 * Follows the batch of range jobs and reports how many have finished,
 * so the console shows real progress instead of stopping at "queued".
 * WaitForPublish then follows the job that publishes the import.
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
     * Steps of the progress bar reported so far.
     *
     * @var int
     */
    private $reported = 0;

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

        // A job that hands on part of its range adds a job to the batch,
        // so the total grows while the import runs. Show the finished
        // share on the planned scale, and never move the bar back.
        // A job counted twice can push the finished count past the total,
        // so the position is capped at the planned scale too.
        $finished = $batch->totalJobs - $batch->pendingJobs;
        $position = $batch->totalJobs > 0 ? intdiv($this->estimate() * $finished, $batch->totalJobs) : 0;
        $position = min($this->estimate(), $position);
        $this->advanceBy = max(0, $position - $this->reported);
        $this->reported = max($this->reported, $position);

        if ($batch->cancelled()) {
            $this->done = true;

            throw ImportBatches::cancelledError($batch);
        }

        // The batch counts deliveries, not ranges: a job delivered twice is
        // counted twice. The ranges' own records decide. When the state is
        // gone, the import is over; WaitForPublish finds out how it ended.
        $elasticsearch = $elasticsearch ?? app(Client::class);
        $state = $this->dispatch->state();
        if ($state === null || ! $state->exists($elasticsearch) || $state->complete($elasticsearch)) {
            // Fill the bar: duplicates may still be running, but no range is.
            $this->advanceBy += $this->estimate() - $this->reported;
            $this->reported = $this->estimate();
            $this->done = true;

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
