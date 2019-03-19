<?php

namespace Matchish\ScoutElasticSearch\Pipelines\Stages;


use Elasticsearch\Client;

/**
 * @internal
 */
final class PullFromSource
{
    /**
     * @var Client
     */
    private $elasticsearch;

    public function __construct(Client $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    public function __invoke($payload)
    {
        [$index, $source] = $payload;

        $totalSearchables = $source::count();
        if ($totalSearchables) {
            $chunkSize = (int) config('scout.chunk.searchable', 500);
            $totalChunks = (int) ceil($totalSearchables / $chunkSize);
            collect(range(1, $totalChunks))->each(function($page) use($source, $chunkSize) {
                $results = $source->forPage($page, $chunkSize)->get();
                $countResults = $results->count();
                if ($countResults == 0) {
                    return false;
                }
                $results->filter->shouldBeSearchable()->searchable();
            });
        }

        return [$index, $source];
    }
}
