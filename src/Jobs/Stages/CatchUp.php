<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Matchish\ScoutElasticSearch\Contracts\SearchableContract;
use Matchish\ScoutElasticSearch\ElasticSearch\BulkResult;
use Matchish\ScoutElasticSearch\ElasticSearch\ImportAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;
use Matchish\ScoutElasticSearch\Searchable\Partitionable;

/**
 * Re-imports rows that changed since the import started, and removes
 * the ones that stopped being searchable or were trashed. Closes the
 * gap for applications that write to the database without firing
 * Eloquent events, so Scout observers never saw the change.
 *
 * A row deleted outright by raw SQL leaves nothing to read, so
 * catch-up cannot remove it.
 *
 * @internal
 *
 * @phpstan-import-type SearchableModel from SearchableContract
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

        /** @var SearchableModel $model */
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

        // A row trashed during the import must leave the index, unless
        // Scout keeps soft-deleted rows searchable. The import query hides
        // trashed rows, so catch-up has to ask for them. A soft delete
        // written by raw SQL may set deleted_at alone, so both columns
        // decide whether a row changed.
        $usesSoftDeletes = $model::usesSoftDelete();
        $removeTrashed = $usesSoftDeletes && ! config('scout.soft_delete', false);
        $deletedAt = $usesSoftDeletes && method_exists($model, 'getDeletedAtColumn')
            ? $model->qualifyColumn($model->getDeletedAtColumn())
            : null;
        $since = $this->since;

        $lastKey = null;
        do {
            $query = $source->query()->reorder()
                ->orderBy($qualifiedKey)
                ->limit($chunkSize);
            if ($deletedAt !== null) {
                $query->withoutGlobalScope(SoftDeletingScope::class)->where(function (Builder $changed) use ($updatedAt, $deletedAt, $since) {
                    $changed->where($updatedAt, '>=', $since)
                        ->orWhere($deletedAt, '>=', $since);
                });
            } else {
                $query->where($updatedAt, '>=', $since);
            }
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

            // Mirror Scout's own observer: a row that stopped being
            // searchable is removed, not just left out.
            [$current, $gone] = $models->partition(function ($model) use ($removeTrashed) {
                /** @var SearchableModel $model */
                $trashed = $removeTrashed && method_exists($model, 'trashed') && $model->trashed();

                return ! $trashed && $model->shouldBeSearchable();
            });
            $this->write($elasticsearch, $current, $gone);
        } while ($models->count() === $chunkSize);
    }

    /**
     * Catch-up reads rows and writes them a moment later, so it is a
     * snapshot too: it must lose to a live change made in between. It
     * read after every range job, so it still beats their copies from
     * the same second.
     *
     * @param  EloquentCollection<int, \Illuminate\Database\Eloquent\Model>  $current
     * @param  EloquentCollection<int, \Illuminate\Database\Eloquent\Model>  $gone
     */
    private function write(Client $elasticsearch, EloquentCollection $current, EloquentCollection $gone): void
    {
        if ($current->isEmpty() && $gone->isEmpty()) {
            return;
        }

        $params = new Bulk(ImportAlias::of($this->index->name()), true, Bulk::WRITER_CATCH_UP);
        $params->index($current->all());
        $params->delete($gone->all());
        /** @var Elasticsearch $elasticResponse */
        $elasticResponse = $elasticsearch->bulk($params->toArray());
        $result = new BulkResult($elasticResponse->asArray());
        if ($result->hasFatalErrors()) {
            throw new \Exception('Bulk catch-up error: '.$result->toJson());
        }
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
