<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Laravel\Scout\Searchable;
use Matchish\ScoutElasticSearch\Database\Scopes\FromScope;
use Matchish\ScoutElasticSearch\Database\Scopes\PageScope;

final class DefaultImportSource implements ImportSource
{
    /**
     * @var int
     */
    const DEFAULT_CHUNK_SIZE = 500;

    /**
     * @var string
     */
    private $className;

    /**
     * @var array<Scope>
     */
    private $scopes;

    /**
     * @var int
     */
    private $totalChunks;

    /**
     * @var int
     */
    private $chunkSize;

    /**
     * @var Scope|null
     */
    private $chunkScope;

    /**
     * DefaultImportSource constructor.
     *
     * @param  string  $className
     * @param  array<Scope>  $scopes
     * @param  Scope|null  $chunkScope
     */
    public function __construct(string $className, array $scopes = [], Scope|null $chunkScope = null)
    {
        $this->className = $className;
        $this->scopes = $scopes;
        $this->chunkScope = $chunkScope;
        $this->chunkSize = self::DEFAULT_CHUNK_SIZE;
        $this->totalChunks = 1;
    }

    /**
     * {@inheritdoc}
     */
    public function syncWithSearchUsingQueue(): ?string
    {
        return $this->model()->syncWithSearchUsingQueue();
    }

    /**
     * {@inheritdoc}
     */
    public function syncWithSearchUsing(): ?string
    {
        return $this->model()->syncWithSearchUsing();
    }

    /**
     * {@inheritdoc}
     */
    public function searchableAs(): string
    {
        return $this->model()->searchableAs();
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalChunks(): int
    {
        return $this->totalChunks;
    }

    /**
     * {@inheritdoc}
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * {@inheritdoc}
     */
    public function setChunkScope(Scope $scope): void
    {
        $this->chunkScope = $scope;
    }

    /**
     * {@inheritdoc}
     */
    public function chunked(): ?ImportSource
    {
        $query = $this->newQuery();
        $totalSearchables = $query->toBase()->getCountForPagination();
        if ($totalSearchables) {
            $configChunkSize = config('scout.chunk.searchable', self::DEFAULT_CHUNK_SIZE);
            $this->chunkSize = is_numeric($configChunkSize) ? intval($configChunkSize) : self::DEFAULT_CHUNK_SIZE;
            $this->totalChunks = (int) ceil($totalSearchables / $this->chunkSize);
            if ($this->model()->getKeyType() !== 'int') {
                $this->chunkScope = new PageScope(0, $this->chunkSize);
            } else {
                $this->chunkScope = new FromScope(0, $this->chunkSize);
            }

            return $this;
        }

        return null;
    }

    /**
     * @return Model|Searchable
     */
    private function model()
    {
        /** @var Model|Searchable */
        return new $this->className;
    }

    /**
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    private function newQuery(): Builder
    {
        $query = $this->className::makeAllSearchableUsing($this->model()->newQuery());
        $softDelete = $this->className::usesSoftDelete() && config('scout.soft_delete', false);
        $query
            ->when($softDelete, function ($query) {
                return $query->withTrashed();
            })
            ->orderBy($this->model()->getQualifiedKeyName());

        $scopes = $this->scopes;

        if ($this->chunkScope) {
            $scopes = array_merge($scopes, [$this->chunkScope]);
        }

        return collect($scopes)->reduce(function ($instance, $scope) {
            $instance->withGlobalScope(get_class($scope), $scope);

            return $instance;
        }, $query);
    }

    /**
     * {@inheritdoc}
     */
    public function get(): EloquentCollection
    {
        /** @var EloquentCollection<int, \Illuminate\Database\Eloquent\Model> $models */
        $models = $this->newQuery()->get();

        return $models;
    }
}
