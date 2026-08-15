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

        /** @var object{total: int|string, non_null: int|string, min_key: int|string|null, max_key: int|string|null}|null $stats */
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
            $min = (int) floor((float) $stats->min_key);
            $max = (int) $stats->max_key;
            $rangesCount = max(1, (int) ceil($nonNull / ($chunkSize * $chunksPerRange)));
            $width = max(1, (int) ceil(($max - $min + 1) / $rangesCount));

            for ($from = $min; $from <= $max; $from += $width) {
                $to = $from + $width;
                $ranges[] = Range::between($from, $to > $max ? null : $to);
            }
        }

        if ((int) $stats->total > $nonNull) {
            $ranges[] = Range::nullBucket();
        }

        return new RangePlan($column, $ranges);
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
