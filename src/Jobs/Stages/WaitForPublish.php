<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Support\Facades\Bus;
use Matchish\ScoutElasticSearch\ElasticSearch\ImportAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\Jobs\ImportBatches;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * Follows the queued job that publishes the import, so the console
 * reports success only once searches use the new index. FinishImport
 * removes the import's state as its last step, after the alias switch,
 * or records in the state why it failed.
 *
 * @internal
 */
final class WaitForPublish implements StageInterface
{
    const POLL_INTERVAL_MICROSECONDS = 1000000;

    /**
     * @var DispatchImportRanges
     */
    private $dispatch;

    /**
     * @var ImportSource
     */
    private $source;

    /**
     * @var Index
     */
    private $index;

    /**
     * @var bool
     */
    private $done = false;

    public function __construct(DispatchImportRanges $dispatch, ImportSource $source, Index $index)
    {
        $this->dispatch = $dispatch;
        $this->source = $source;
        $this->index = $index;
    }

    public function handle(?Client $elasticsearch = null): void
    {
        $elasticsearch = $elasticsearch ?? app(Client::class);
        $state = $this->dispatch->state();
        if ($state === null) {
            $this->done = true;

            return;
        }

        $batchId = $this->dispatch->batchId();
        $batch = $batchId !== null ? Bus::findBatch($batchId) : null;
        if ($batch !== null && $batch->cancelled()) {
            $this->done = true;

            throw ImportBatches::cancelledError($batch);
        }

        $failure = $state->failure($elasticsearch);
        if ($failure !== null) {
            $this->done = true;

            throw new \Exception(sprintf(
                'The import could not be published: %s. The search alias was not switched, so the old index still serves searches.',
                $failure
            ));
        }

        if ($state->exists($elasticsearch)) {
            usleep(self::POLL_INTERVAL_MICROSECONDS);

            return;
        }

        // The state is gone: either this import was published, or a newer
        // import removed it together with this import's index.
        $this->done = true;
        if (! $this->published($elasticsearch)) {
            throw new \Exception(
                'A newer import for the same index replaced this one before it was published. The search alias was not changed by this import.'
            );
        }
    }

    /**
     * The switch gives the import's index the search alias and takes
     * its import alias away, in one atomic step.
     */
    private function published(Client $elasticsearch): bool
    {
        $name = $this->index->name();
        try {
            /** @var Elasticsearch $response */
            $response = $elasticsearch->indices()->getAlias(['index' => $name]);
        } catch (ClientResponseException $e) {
            return false;
        }
        $aliases = $response->asArray()[$name]['aliases'] ?? null;

        return is_array($aliases)
            && array_key_exists($this->source->searchableAs(), $aliases)
            && ! array_key_exists(ImportAlias::of($name), $aliases);
    }

    public function title(): string
    {
        return 'Switching to the new index';
    }

    public function estimate(): int
    {
        return 1;
    }

    public function advance(): int
    {
        return $this->done ? 1 : 0;
    }

    public function completed(): bool
    {
        return $this->done;
    }
}
