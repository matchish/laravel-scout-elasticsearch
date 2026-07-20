<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * Cancels a still-running parallel import for the same index, so a
 * new import supersedes it. Cancelled ImportRange jobs stop at their
 * next chunk boundary; because they write to the concrete index of
 * the superseded import, late writes can never reach the new index.
 *
 * @internal
 */
final class CancelPreviousImport implements StageInterface
{
    /**
     * @var ImportSource
     */
    private $source;

    public function __construct(ImportSource $source)
    {
        $this->source = $source;
    }

    public function handle(Client $elasticsearch): void
    {
        if (! app(BatchRepository::class) instanceof DatabaseBatchRepository) {
            return;
        }

        /** @var string $table */
        $table = config('queue.batching.table', 'job_batches');
        /** @var string|null $connection */
        $connection = config('queue.batching.database');

        try {
            $ids = DB::connection($connection)
                ->table($table)
                ->where('name', 'scout-import:'.$this->source->searchableAs())
                ->whereNull('cancelled_at')
                ->whereNull('finished_at')
                ->pluck('id');
        } catch (QueryException $e) {
            return; // the batches table does not exist yet — nothing to cancel
        }

        foreach ($ids as $id) {
            if (! is_string($id) && ! is_int($id)) {
                continue;
            }
            $batch = Bus::findBatch((string) $id);
            if ($batch !== null) {
                $batch->cancel();
            }
        }
    }

    public function title(): string
    {
        return 'Stop previous import';
    }

    public function estimate(): int
    {
        return 1;
    }

    public function advance(): int
    {
        return 1;
    }

    public function completed(): bool
    {
        return true;
    }
}
