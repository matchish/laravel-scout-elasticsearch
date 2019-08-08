<?php

namespace Matchish\ScoutElasticSearch\Console\Commands;

class DefaultImportSource implements ImportSource
{
    const DEFAULT_CHUNK_SIZE = 500;

    private $className;
    private $scopes;

    /**
     * DefaultImportSource constructor.
     * @param string $className
     * @param array $scopes
     */
    public function __construct(string $className, array $scopes = [])
    {
        $this->className = $className;
        $this->scopes = $scopes;
    }

    public function syncWithSearchUsingQueue()
    {
        return $this->model()->syncWithSearchUsingQueue();
    }

    public function syncWithSearchUsing()
    {
        return $this->model()->syncWithSearchUsing();
    }

    public function searchableAs(): string
    {
        return $this->model()->searchableAs();
    }

    public function chunked()
    {
        $query = $this->newQuery();
        $searchable = $this->model();
        $totalSearchables = $query->count();
        if ($totalSearchables) {
            $chunkSize = (int) config('scout.chunk.searchable', self::DEFAULT_CHUNK_SIZE);
            $cloneQuery = clone $query;
            $cloneQuery->joinSub('SELECT @row :=0, 1 as temp', 'r', 'r.temp', 'r.temp')
                ->selectRaw("@row := @row +1 AS rownum, {$searchable->getKeyName()}");
            $ids = \DB::query()->fromSub($cloneQuery, 'ranked')->whereRaw("rownum %{$chunkSize} =0")->pluck('id');
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

            return collect($pairs)->map(function ($pair) {
                [$start, $end] = $pair;
                $chunkScope = new ChunkScope($start, $end);

                return new static($this->className, [$chunkScope]);
            });
        } else {
            return collect();
        }
    }

    /**
     * @return mixed
     */
    private function model()
    {
        return new $this->className;
    }

    private function newQuery()
    {
        $query = $this->model()->newQuery();
        $softDelete = config('scout.soft_delete', false);
        $query
            ->when($softDelete, function ($query) {
                return $query->withTrashed();
            })
            ->orderBy($this->model()->getKeyName());
        $scopes = $this->scopes;

        return collect($scopes)->reduce(function ($instance, $scope) {
            $instance->withGlobalScope($scope->key(), $scope);

            return $instance;
        }, $query);
    }

    public function get()
    {
        return $this->newQuery()->get();
    }
}
