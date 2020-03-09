<?php

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

interface ImportSource
{
    public function syncWithSearchUsingQueue(): ?string;

    public function syncWithSearchUsing(): ?string;

    public function searchableAs(): string;

    public function chunked(): Collection;

    /**
     * @return Builder[]|EloquentCollection
     */
    public function get();
}
