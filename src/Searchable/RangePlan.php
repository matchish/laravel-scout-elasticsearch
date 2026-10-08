<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

/**
 * The partition column and the ranges that together cover
 * every row of an import source.
 */
final class RangePlan
{
    /**
     * @var string
     */
    private $column;

    /**
     * @var array<int, Range>
     */
    private $ranges;

    /**
     * @param  string  $column
     * @param  array<int, Range>  $ranges
     */
    public function __construct(string $column, array $ranges)
    {
        $this->column = $column;
        $this->ranges = $ranges;
    }

    public function column(): string
    {
        return $this->column;
    }

    /**
     * @return array<int, Range>
     */
    public function ranges(): array
    {
        return $this->ranges;
    }

    public function isEmpty(): bool
    {
        return $this->ranges === [];
    }
}
