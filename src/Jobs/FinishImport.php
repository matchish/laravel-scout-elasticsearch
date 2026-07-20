<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\Stages\CatchUp;
use Matchish\ScoutElasticSearch\Jobs\Stages\RefreshIndex;
use Matchish\ScoutElasticSearch\Jobs\Stages\SwitchToNewAndRemoveOldIndex;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * Runs after every ImportRange job of a parallel import has finished:
 * optionally catches up rows changed during the import, refreshes
 * the new index, and atomically switches the aliases to it.
 *
 * @internal
 */
final class FinishImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var ImportSource
     */
    private $source;

    /**
     * @var Index
     */
    private $index;

    /**
     * @var \DateTimeInterface|null
     */
    private $catchUpSince;

    public function __construct(ImportSource $source, Index $index, ?\DateTimeInterface $catchUpSince = null)
    {
        $this->source = $source;
        $this->index = $index;
        $this->catchUpSince = $catchUpSince;
    }

    public function handle(Client $elasticsearch): void
    {
        if ($this->catchUpSince !== null) {
            (new CatchUp($this->source, $this->index, $this->catchUpSince))->handle($elasticsearch);
        }
        (new RefreshIndex($this->index))->handle($elasticsearch);
        (new SwitchToNewAndRemoveOldIndex($this->source, $this->index))->handle($elasticsearch);
    }
}
