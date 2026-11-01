<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Database\Eloquent\Builder;

/**
 * An import source that exposes its full query, so the import
 * can be split into key ranges and processed in parallel.
 */
interface Partitionable
{
    /**
     * The complete import query with all scopes applied.
     *
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function query(): Builder;
}
