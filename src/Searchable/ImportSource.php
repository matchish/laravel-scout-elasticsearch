<?php
declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\LazyCollection;

interface ImportSource
{
    public function syncWithSearchUsingQueue(): ?string;

    public function syncWithSearchUsing(): ?string;

    public function searchableAs(): string;

    public function chunked(): LazyCollection;

    public function get(): EloquentCollection;

    public function count(): int;

    public function last(): ?object;
}
