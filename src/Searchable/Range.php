<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

/**
 * A half-open interval [from, to) of partition key values.
 *
 * A null $to means the range is open-ended, so rows inserted after
 * planning with keys above the last anchor are still covered.
 * A null-bucket range matches rows where the partition key IS NULL.
 */
final class Range
{
    /**
     * @var int|null
     */
    private $from;

    /**
     * @var int|null
     */
    private $to;

    /**
     * @var bool
     */
    private $nullBucket;

    private function __construct(?int $from, ?int $to, bool $nullBucket)
    {
        $this->from = $from;
        $this->to = $to;
        $this->nullBucket = $nullBucket;
    }

    public static function between(int $from, ?int $to): self
    {
        return new self($from, $to, false);
    }

    public static function nullBucket(): self
    {
        return new self(null, null, true);
    }

    public function from(): ?int
    {
        return $this->from;
    }

    public function to(): ?int
    {
        return $this->to;
    }

    public function isNullBucket(): bool
    {
        return $this->nullBucket;
    }
}
