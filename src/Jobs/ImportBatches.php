<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Finds the job batches of parallel imports. Batches are named after
 * the index they build, which is what lets a later import supersede
 * an earlier one and lets the status command report progress.
 *
 * @internal
 */
final class ImportBatches
{
    public static function name(string $searchableAs): string
    {
        return 'scout-import:'.$searchableAs;
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
