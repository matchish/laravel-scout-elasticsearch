<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elasticsearch\Client;
use Matchish\ScoutElasticSearch\ElasticSearch\DefaultAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Create;
use Matchish\ScoutElasticSearch\ElasticSearch\WriteAlias;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @internal
 */
final class CreateWriteIndex
{
    /**
     * @var ImportSource
     */
    private $source;
    /**
     * @var Index
     */
    private $index;

    /**
     * @param ImportSource $source
     * @param Index $index
     */
    public function __construct(ImportSource $source, Index $index)
    {
        $this->source = $source;
        $this->index = $index;
    }

    public function handle(Client $elasticsearch): void
    {
        $source = $this->source;
        $this->index->addAlias(new WriteAlias(new DefaultAlias($source->searchableAs())));

        $params = new Create(
            $this->index->name(),
            $this->index->config()
        );

        $elasticsearch->indices()->create($params->toArray());
    }

    public function title(): string
    {
        return 'Create write index';
    }

    public function estimate(): int
    {
        return 1;
    }
}
