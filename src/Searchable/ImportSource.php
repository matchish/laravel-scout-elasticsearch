<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Scope;

interface ImportSource
{
    /**
     * @return string|null
     */
    public function syncWithSearchUsingQueue(): ?string;

    /**
     * @return string|null
     */
    public function syncWithSearchUsing(): ?string;

    /**
     * @return string
     */
    public function searchableAs(): string;

    /**
     * @return ImportSource|null
     */
    public function chunked(): ?ImportSource;

    /**
     * @param  Scope  $scope
     * @return void
     */
    public function setChunkScope(Scope $scope): void;

    /**
     * @return int
     */
    public function getTotalChunks(): int;

    /**
     * @return int
     */
    public function getChunkSize(): int;

    /**
     * @return EloquentCollection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function get(): EloquentCollection;
}
