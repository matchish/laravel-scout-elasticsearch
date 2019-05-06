<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elasticsearch\Client;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\ElasticSearch\WriteAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\DefaultAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Create;

/**
 * @internal
 */
final class CreateWriteIndex
{
    /**
     * @var Model
     */
    private $searchable;
    /**
     * @var Index
     */
    private $index;

    /**
     * @param Model $searchable
     * @param Index $index
     */
    public function __construct(Model $searchable, Index $index)
    {
        $this->searchable = $searchable;
        $this->index = $index;
    }

    public function handle(Client $elasticsearch): void
    {
        /** @var Searchable $searchable */
        $searchable = $this->searchable;
        $this->index->addAlias(new WriteAlias(new DefaultAlias($searchable->searchableAs())));

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
