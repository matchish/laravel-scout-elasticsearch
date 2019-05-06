<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Laravel\Scout\Searchable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @internal
 */
final class PullFromSource
{
    const DEFAULT_CHUNK_SIZE = 500;

    /**
     * @var Builder
     */
    private $query;

    /**
     * @param Builder $query
     */
    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function handle(): void
    {
        $results = $this->query->get();
        $results->filter->shouldBeSearchable()->searchable();
    }

    public function estimate(): int
    {
        return 1;
    }

    public function title(): string
    {
        return 'Indexing...';
    }

    /**
     * @param Model $searchable
     * @return Collection
     */
    public static function chunked(Model $searchable): Collection
    {
        /** @var Searchable $searchable */
        $softDelete = config('scout.soft_delete', false);
        $query = $searchable->newQuery()
            ->when($softDelete, function ($query) {
                return $query->withTrashed();
            })
            ->orderBy($searchable->getKeyName());
        $totalSearchables = $query->count();
        if ($totalSearchables) {
            $chunkSize = (int) config('scout.chunk.searchable', self::DEFAULT_CHUNK_SIZE);
            $totalChunks = (int) ceil($totalSearchables / $chunkSize);

            return collect(range(1, $totalChunks))->map(function ($page) use ($query, $chunkSize) {
                $clone = (clone $query)->forPage($page, $chunkSize);

                return new static($clone);
            });
        } else {
            return collect();
        }
    }
}
