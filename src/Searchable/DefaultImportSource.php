<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Matchish\ScoutElasticSearch\Database\Scopes\FromScope;
use Matchish\ScoutElasticSearch\Database\Scopes\PageScope;

final class DefaultImportSource implements ImportSource
{
    const DEFAULT_CHUNK_SIZE = 500;
    const DEFAULT_CHUNK_HANDLERS = 1;

    /**
     * @var string
     */
    private $className;
    /**
     * @var array
     */
    private $scopes;
    /**
     * @var ?object
     */
    private $last;
    /**
     * @var int
     */
    private $count;

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

    public function syncWithSearchUsingQueue(): ?string
    {
        return $this->model()->syncWithSearchUsingQueue();
    }

    public function syncWithSearchUsing(): ?string
    {
        return $this->model()->syncWithSearchUsing();
    }

    public function syncWithSearchUsingQueues(): array
    {
        return [
            'import-1',
            'import-2',
            'import-3',
            'import-4',
            'import-5',
        ];
    }

    public function searchableAs(): string
    {
        return $this->model()->searchableAs();
    }

    public function chunked(): LazyCollection
    {
        return LazyCollection::make(function () {
            $chunkSize = (int) config('scout.chunk.searchable', self::DEFAULT_CHUNK_SIZE);
            $workers = (int) config('scout.chunk.handlers', self::DEFAULT_CHUNK_HANDLERS);
            $from = null;
            while (true) {
                $chunks = [];
                for ($page = 1; $page <= $workers; $page++) {
                    $chunkScopes = [];
                    if ($from) {
                        $chunkScopes[] = $from;
                    }
                    $chunkScopes[] = new PageScope($page, $chunkSize);
                    $chunk = new static($this->className, array_merge($this->scopes, $chunkScopes));
                    if ($page === 1 && ! $chunk->count()) {
                        break 2;
                    }
                    $chunks[] = $chunk;
                }
                yield collect($chunks);

                $last = isset($chunk) ? $chunk->last() : null;
                if ($last instanceof Model) {
                    $from = new FromScope($last->getKey());
                } else {
                    break;
                }
            }
        });
    }

    /**
     * @return mixed
     */
    private function model()
    {
        return new $this->className;
    }

    private function newQuery(): Builder
    {
        $query = $this->model()->newQuery();
        $softDelete = $this->className::usesSoftDelete() && config('scout.soft_delete', false);
        $query
            ->when($softDelete, function ($query) {
                return $query->withTrashed();
            })
            ->orderBy($this->model()->getKeyName());
        $scopes = $this->scopes;

        return collect($scopes)->reduce(function ($instance, $scope) {
            $instance->withGlobalScope(get_class($scope), $scope);

            return $instance;
        }, $query);
    }

    public function get(): EloquentCollection
    {
        /** @var EloquentCollection $models */
        $models = $this->newQuery()->get();
        $this->last = $models->last();
        $this->count = $models->count();

        return $models;
    }

    public function count(): int
    {
        if (isset($this->count)) {
            return $this->count;
        }

        return $this->newQuery()->count();
    }

    public function chunksCount(): int
    {
        $chunkSize = (int) config('scout.chunk.searchable', self::DEFAULT_CHUNK_SIZE);

        return (int) ceil($this->count() / $chunkSize);
    }

    public function last(): ?object
    {
        if ($this->last) {
            return $this->last;
        }

        return $this->get()->last();
    }
}
