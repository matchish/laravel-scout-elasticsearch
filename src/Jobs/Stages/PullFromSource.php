<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elasticsearch\Common\Exceptions\ServerErrorResponseException;
use Illuminate\Support\LazyCollection;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @internal
 */
final class PullFromSource
{
    /**
     * @var LazyCollection
     */
    private $source;
    /**
     * @var int
     */
    private $estimate;

    /**
     * @param LazyCollection $source
     * @param int $estimate
     */
    public function __construct($source, $estimate)
    {
        $this->source = $source;
        $this->estimate = $estimate;
    }

    /**
     * @return LazyCollection
     */
    public function handle()
    {
        return LazyCollection::make(function () {
            foreach ($this->source as $chunk) {
                $futures = [];
                foreach ($chunk as $page) {
                    $results = $page->get()->filter->shouldBeSearchable();
                    if (! $results->isEmpty()) {
                        $futures[] = $results->first()->searchableUsing()->updateAsync($results);
                    }
                }
                foreach ($futures as $future) {
                    if (isset($future['errors']) && $future['errors']) {
                        throw new ServerErrorResponseException(json_encode($future, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                    }
                    yield 1;
                }
            }
        });
    }

    public function estimate(): int
    {
        return $this->estimate;
    }

    public function title(): string
    {
        return 'Indexing...';
    }

    /**
     * @param ImportSource $source
     * @return static
     */
    public static function chunked(ImportSource $source): PullFromSource
    {
        $chunked = $source->chunked();

        return new static($chunked, $source->chunksCount());
    }
}
