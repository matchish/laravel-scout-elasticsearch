<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Imtigger\LaravelJobStatus\Trackable;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;
use Illuminate\Bus\Queueable;

/**
 * @internal
 */
final class PullFromSource
{

    use Queueable;
    private Collection $source;

    public function __construct(Collection $source)
    {
        $this->source = $source;
    }

    public function handle(): void
    {
        $this->source->each(function($chunk) {
            $results = $chunk->get()->filter->shouldBeSearchable();
            if (! $results->isEmpty()) {
                $results->first()->searchableUsing()->update($results);
            }
        });
    }

    public function estimate(): int
    {
        return $this->source->count();
    }

    public function title(): string
    {
        return 'Indexing...';
    }

    /**
     * @param ImportSource $source
     * @return LazyCollection
     */
    public static function chunked(ImportSource $source): LazyCollection
    {
        return $source->chunked()->map(function ($chunks) {
            return new PullFromSource($chunks);
        });
    }
}
