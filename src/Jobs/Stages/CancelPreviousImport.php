<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Matchish\ScoutElasticSearch\Jobs\ImportBatches;
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

    public function handle(?Client $elasticsearch = null): void
    {
        foreach (ImportBatches::unfinished($this->source->searchableAs()) as $batch) {
            $batch->cancel();
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
