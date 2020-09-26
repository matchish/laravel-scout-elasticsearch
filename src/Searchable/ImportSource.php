<?php

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;

interface ImportSource
{
    public function syncWithSearchUsingQueue(): ?string;

    public function syncWithSearchUsing(): ?string;

    public function searchableAs(): string;

    public function chunked(): Collection;

    public function getChunkSize(): int;

    public function get(): EloquentCollection;

    public function defineIndex(): Index;
}
