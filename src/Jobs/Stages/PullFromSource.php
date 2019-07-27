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
            $chunkSize = (int)config('scout.chunk.searchable', self::DEFAULT_CHUNK_SIZE);
            $cloneQuery = clone $query;
            $cloneQuery->joinSub('SELECT @row :=0, 1 as temp', 'r', 'r.temp', 'r.temp')
                ->selectRaw("@row := @row +1 AS rownum, {$searchable->getKeyName()}");
            $ids = \DB::query()->fromSub($cloneQuery, 'ranked')->whereRaw("rownum %{$chunkSize} =1 and rownum != 1")->pluck('id');
            $pairs = [];
            $lastId = null;
            foreach ($ids as $id) {
                if ($lastId) {
                    $pairs[] = [$lastId, $id];
                } else {
                    $pairs[] = [null, $id];
                }
                $lastId = $id;
            }
            $pairs[] = [$lastId, null];
            return collect($pairs)->map(function ($pair) use ($query, $searchable) {
                list($start, $end) = $pair;
                $clone = (clone $query)
                    ->when(!is_null($start), function ($query) use ($start, $searchable) {
                        return $query->where($searchable->getKeyName(), '>', $start);
                    })
                    ->when(!is_null($end), function ($query) use ($end, $searchable) {
                        return $query->where($searchable->getKeyName(), '<=', $end);
                    });
                return new static($clone);
            });
        } else {
            return collect();
        }
    }
}
