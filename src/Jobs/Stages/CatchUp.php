<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Support\Facades\Log;
use Matchish\ScoutElasticSearch\ElasticSearch\BulkResult;
use Matchish\ScoutElasticSearch\ElasticSearch\ImportAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;
use Matchish\ScoutElasticSearch\Searchable\Partitionable;

/**
 * Re-imports rows whose updated_at changed since the import started.
 * Closes the gap for applications that write to the database without
 * firing Eloquent events, so Scout observers never saw the change.
 *
 * @internal
 */
final class CatchUp implements StageInterface
{
    /**
     * @var ImportSource
     */
    private $source;

    /**
     * @var Index
     */
    private $index;

    /**
     * @var \DateTimeInterface
     */
    private $since;

    /**
     * The catch-up window opens when the stage is composed, which
     * happens right before the import starts running. An earlier
     * timestamp only widens the window — the safe direction.
     */
    public function __construct(ImportSource $source, Index $index, ?\DateTimeInterface $since = null)
    {
        $this->source = $source;
        $this->index = $index;
        $this->since = $since ?? new \DateTimeImmutable('now');
    }

    public function handle(Client $elasticsearch): void
    {
        $source = $this->source;
        if (! $source instanceof Partitionable) {
            return;
        }

        $model = $source->query()->getModel();
        if (! $model->usesTimestamps() || $model->getUpdatedAtColumn() === null) {
            Log::warning('scout:import catch-up skipped: the model does not use an updated_at timestamp.', [
                'model' => get_class($model),
            ]);

            return;
        }

        $updatedAt = $model->qualifyColumn($model->getUpdatedAtColumn());
        $qualifiedKey = $model->getQualifiedKeyName();
        $configChunkSize = config('scout.chunk.searchable', 500);
        $chunkSize = is_numeric($configChunkSize) ? (int) $configChunkSize : 500;

        $lastKey = null;
        do {
            $query = $source->query()->reorder()
                ->where($updatedAt, '>=', $this->since)
                ->orderBy($qualifiedKey)
                ->limit($chunkSize);
            if ($lastKey !== null) {
                $query->where($qualifiedKey, '>', $lastKey);
            }
            $models = $query->get();
            if ($models->isEmpty()) {
                return;
            }

            /** @var \Illuminate\Database\Eloquent\Model $last */
            $last = $models->last();
            $lastKey = $last->getKey();

            $searchable = $models->filter->shouldBeSearchable();
            if ($searchable->isNotEmpty()) {
                // Catch-up re-reads rows as they are now, so it carries
                // live changes rather than a snapshot.
                $params = new Bulk(ImportAlias::of($this->index->name()), true, Bulk::WRITER_LIVE);
                $params->index($searchable->all());
                /** @var Elasticsearch $elasticResponse */
                $elasticResponse = $elasticsearch->bulk($params->toArray());
                $result = new BulkResult($elasticResponse->asArray());
                if ($result->hasFatalErrors()) {
                    throw new \Exception('Bulk catch-up error: '.$result->toJson());
                }
            }
        } while ($models->count() === $chunkSize);
    }

    public function title(): string
    {
        return 'Catching up changed rows';
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
