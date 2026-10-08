<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Searchable;

use Illuminate\Database\Eloquent\Model;

/**
 * Splits an import source into key ranges using one aggregate query
 * (COUNT, MIN, MAX over the partition column). Ranges are equal-width
 * in key space, so they do not need a second pass over the data;
 * uneven ranges only affect balance, never correctness.
 */
final class RangePlanner
{
    /**
     * @param  Partitionable  $source
     * @param  int  $chunkSize  rows per bulk request inside a range job
     * @param  int  $chunksPerRange  target amount of chunks per range
     * @return RangePlan
     */
    public static function plan(Partitionable $source, int $chunkSize, int $chunksPerRange): RangePlan
    {
        $query = $source->query();
        $model = $query->getModel();
        $column = self::partitionColumn($model);

        $base = $query->toBase()
            ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order']);
        $wrapped = $base->getGrammar()->wrap($model->qualifyColumn($column));

        /** @var object{total: int|string, non_null: int|string, min_key: int|float|string|null, max_key: int|float|string|null}|null $stats */
        $stats = $base->selectRaw(
            "count(*) as total, count({$wrapped}) as non_null, min({$wrapped}) as min_key, max({$wrapped}) as max_key"
        )->first();

        if ($stats === null || (int) $stats->total === 0) {
            return new RangePlan($column, []);
        }

        $ranges = [];
        $nonNull = (int) $stats->non_null;

        if ($nonNull > 0) {
            if (! is_numeric($stats->min_key) || ! is_numeric($stats->max_key)) {
                throw new \InvalidArgumentException(sprintf(
                    'Parallel import supports only numeric partition keys, but column [%s] of model [%s] holds non-numeric values.',
                    $column,
                    get_class($model)
                ));
            }
            $ranges = self::split(
                self::approximate($stats->min_key),
                self::approximate($stats->max_key),
                max(1, (int) ceil($nonNull / ($chunkSize * $chunksPerRange)))
            );
        }

        if ((int) $stats->total > $nonNull) {
            $ranges[] = Range::nullBucket();
        }

        return new RangePlan($column, $ranges);
    }

    /**
     * Cuts the key space into at most $count ranges. The first range has
     * no lower bound and the last has no upper bound, and each boundary
     * is shared exactly by the two ranges beside it, so every key falls
     * in exactly one range whatever the boundaries are. $min and $max
     * only steer the balance: rounding them, or a row that arrives
     * below $min or above $max during the import, cannot lose a row.
     *
     * @return array<int, Range>
     */
    private static function split(?int $min, ?int $max, int $count): array
    {
        if ($min === null || $max === null || $count < 2 || $max <= $min) {
            return [Range::between(null, null)];
        }

        // Float precision is enough here: the width only spreads the work.
        $width = (int) min(4.6e18, max(1.0, ceil(((float) $max - (float) $min) / $count)));

        $ranges = [];
        $from = null;
        $boundary = $min;
        for ($i = 1; $i < $count; $i++) {
            if ($boundary > PHP_INT_MAX - $width) {
                break; // the next boundary would overflow
            }
            $boundary += $width;
            if ($boundary > $max) {
                break;
            }
            $ranges[] = Range::between($from, $boundary);
            $from = $boundary;
        }
        $ranges[] = Range::between($from, null);

        return $ranges;
    }

    /**
     * The key as an integer, or null when it cannot be one. Exact for
     * integers and integer strings; anything else is floored through a
     * float, which is precise enough to steer the balance.
     *
     * @param  int|float|string  $value
     */
    private static function approximate($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value)) {
            $int = filter_var($value, FILTER_VALIDATE_INT);
            if ($int !== false) {
                return $int;
            }
        }
        $float = (float) $value;
        // Stay clear of the edges of the integer range, where casting a
        // float is undefined.
        if (! is_finite($float) || abs($float) >= 9.2e18) {
            return null;
        }

        return (int) floor($float);
    }

    /**
     * A declared searchablePartitionKey() wins; an integer primary
     * key is the automatic fallback; anything else needs configuration.
     *
     * @param  Model  $model
     * @return string
     */
    private static function partitionColumn(Model $model): string
    {
        if (method_exists($model, 'searchablePartitionKey')) {
            return (string) $model->searchablePartitionKey();
        }

        if ($model->getKeyType() === 'int') {
            return $model->getKeyName();
        }

        throw new \InvalidArgumentException(sprintf(
            'Parallel import requires a numeric partition key, but model [%s] has a non-integer primary key. Add a searchablePartitionKey() method returning the name of an indexed, immutable, numeric column.',
            get_class($model)
        ));
    }
}
