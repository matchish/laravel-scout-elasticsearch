<?php

declare(strict_types=1);

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Get;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Update;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @internal
 */
final class SwitchToNewAndRemoveOldIndex implements StageInterface
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
     * @param  ImportSource  $source
     * @param  Index  $index
     */
    public function __construct(ImportSource $source, Index $index)
    {
        $this->source = $source;
        $this->index = $index;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Client $elasticsearch): void
    {
        $source = $this->source;
        $params = Get::anyIndex($source->searchableAs());
        /** @var Elasticsearch $elasticResponse */
        $elasticResponse = $elasticsearch->indices()->getAlias($params->toArray());
        $response = $elasticResponse->asArray();

        $params = new Update();
        foreach ($response as $indexName => $alias) {
            if ($indexName != $this->index->name()) {
                $params->removeIndex((string) $indexName);
            } else {
                $params->add((string) $indexName, $source->searchableAs());
            }
        }
        $elasticsearch->indices()->updateAliases($params->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function estimate(): int
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     */
    public function title(): string
    {
        return 'Switching to the new index';
    }

    /**
     * {@inheritdoc}
     */
    public function advance(): int
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     */
    public function completed(): bool
    {
        return true;
    }
}
