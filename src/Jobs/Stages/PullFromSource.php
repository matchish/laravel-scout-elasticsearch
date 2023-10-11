<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Collection;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @internal
 */
final class PullFromSource implements StageInterface
{
    /**
     * @var ImportSource
     */
    private $source;

    /**
     * @param  ImportSource  $source
     */
    public function __construct(ImportSource $source)
    {
        $this->source = $source;
    }

    public function handle(Client $elasticsearch = null): void
    {
        $results = $this->source->get()->filter->shouldBeSearchable();
        if (! $results->isEmpty()) {
            $results->first()->searchableUsing()->update($results);
        }
    }

    public function estimate(): int
    {
        return 1;
    }

    public function title(): string
    {
        return 'Indexing...';
    }

    /**
     * @param  ImportSource  $source
     * @return Collection
     */
    public static function chunked(ImportSource $source): Collection
    {
        return $source->chunked()->map(function ($chunk) {
            return new static($chunk);
        });
    }
}
