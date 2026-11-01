<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Creates and finds the job batches of parallel imports. Batches are
 * named after the index they build, which is what lets a later import
 * supersede an earlier one. Each batch also says which import it runs,
 * which is what lets the status command read that import's records.
 *
 * @internal
 */
final class ImportBatches
{
    private const IMPORT_OPTION = 'scout_import';

    public static function name(string $searchableAs): string
    {
        return 'scout-import:'.$searchableAs;
    }

    /**
     * The batch of range jobs for an import that writes to $importIndex
     * and planned $ranges ranges.
     *
     * @param  array<int, ImportRange>  $jobs
     */
    public static function make(array $jobs, string $searchableAs, string $importIndex, int $ranges): PendingBatch
    {
        return Bus::batch($jobs)
            ->name(self::name($searchableAs))
            ->withOption(self::IMPORT_OPTION, ['index' => $importIndex, 'ranges' => $ranges]);
    }

    /**
     * The index that the import of $batch writes to and the number of
     * ranges it planned, or null for a batch that does not say.
     *
     * @return array{index: string, ranges: int}|null
     */
    public static function import(Batch $batch): ?array
    {
        $import = $batch->options[self::IMPORT_OPTION] ?? null;
        $index = is_array($import) ? ($import['index'] ?? null) : null;
        $ranges = is_array($import) ? ($import['ranges'] ?? null) : null;
        if (! is_string($index) || ! is_int($ranges)) {
            return null;
        }

        return ['index' => $index, 'ranges' => $ranges];
    }

    /**
     * Why a cancelled import batch stopped, for the console to report.
     */
    public static function cancelledError(Batch $batch): \Exception
    {
        if ($batch->failedJobs > 0) {
            return new \Exception(sprintf(
                '%d of %d range jobs failed, so the search alias was not switched. The old index still serves searches; check the failed_jobs table for the cause.',
                $batch->failedJobs,
                $batch->totalJobs
            ));
        }

        return new \Exception(
            'This import was cancelled before it finished, most likely because a newer import for the same index started. The search alias was not changed.'
        );
    }

    /**
     * The most recent import batch for an index, finished or not.
     */
    public static function latest(string $searchableAs): ?Batch
    {
        return self::find($searchableAs, false)[0] ?? null;
    }

    /**
     * Import batches for an index that are still running.
     *
     * @return array<int, Batch>
     */
    public static function unfinished(string $searchableAs): array
    {
        return self::find($searchableAs, true);
    }

    /**
     * @return array<int, Batch>
     */
    private static function find(string $searchableAs, bool $onlyUnfinished): array
    {
        if (! app(BatchRepository::class) instanceof DatabaseBatchRepository) {
            return [];
        }

        /** @var string $table */
        $table = config('queue.batching.table', 'job_batches');
        /** @var string|null $connection */
        $connection = config('queue.batching.database');

        try {
            $query = DB::connection($connection)
                ->table($table)
                ->where('name', self::name($searchableAs));

            if ($onlyUnfinished) {
                $query->whereNull('cancelled_at')->whereNull('finished_at');
            }

            $ids = $query->orderByDesc('created_at')->pluck('id');
        } catch (QueryException $e) {
            return []; // the batches table does not exist yet
        }

        $batches = [];
        foreach ($ids as $id) {
            if (! is_string($id) && ! is_int($id)) {
                continue;
            }
            $batch = Bus::findBatch((string) $id);
            if ($batch !== null) {
                $batches[] = $batch;
            }
        }

        return $batches;
    }
}
