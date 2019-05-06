<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elasticsearch\Client;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Get;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Update;

/**
 * @internal
 */
final class SwitchToNewAndRemoveOldIndex
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
        $params = Get::anyIndex($searchable->searchableAs());
        $response = $elasticsearch->indices()->getAliases($params->toArray());

        $params = new Update();
        foreach ($response as $indexName => $alias) {
            if ($indexName != $this->index->name()) {
                $params->removeIndex($indexName);
            } else {
                $params->add((string) $indexName, $searchable->searchableAs());
            }
        }
        $elasticsearch->indices()->updateAliases($params->toArray());
    }

    public function estimate(): int
    {
        return 1;
    }

    public function title(): string
    {
        return 'Switching to the new index';
    }
}
