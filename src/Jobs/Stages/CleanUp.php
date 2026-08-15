<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias\Get as GetAliasParams;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Delete as DeleteIndexParams;
use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @internal
 */
final class CleanUp implements StageInterface
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

    public function handle(Client $elasticsearch): void
    {
        $source = $this->source;
        $params = GetAliasParams::anyIndex($source->searchableAs());
        try {
            /** @var Elasticsearch $elasticResponse */
            $elasticResponse = $elasticsearch->indices()->getAlias($params->toArray());
            $response = $elasticResponse->asArray();
        } catch (ClientResponseException $e) {
            $response = [];
        }
        foreach ($response as $indexName => $data) {
            foreach ($data['aliases'] as $alias => $config) {
                if (array_key_exists('is_write_index', $config) && $config['is_write_index']) {
                    $params = new DeleteIndexParams((string) $indexName);
                    $elasticsearch->indices()->delete($params->toArray());
                    continue 2;
                }
            }
        }

        $this->reclaimLeakedIndices($elasticsearch);
    }

    /**
     * An import that dies between creating its index and finishing —
     * a crashed worker, a lost process — leaks that index: no alias
     * routes to it, so nothing else will ever delete it. Reclaim such
     * indices here, but only ones carrying this package's provenance
     * marker; an unmarked index is not ours to delete.
     */
    private function reclaimLeakedIndices(Client $elasticsearch): void
    {
        try {
            /** @var Elasticsearch $elasticResponse */
            $elasticResponse = $elasticsearch->indices()->get([
                'index' => $this->source->searchableAs().'_*',
            ]);
            $indices = $elasticResponse->asArray();
        } catch (ClientResponseException $e) {
            return;
        }

        foreach ($indices as $indexName => $info) {
            $marked = (bool) ($info['mappings']['_meta']['scout_import'] ?? false);
            $aliases = $info['aliases'] ?? [];
            if ($marked && $aliases === []) {
                $params = new DeleteIndexParams((string) $indexName);
                $elasticsearch->indices()->delete($params->toArray());
            }
        }
    }

    public function title(): string
    {
        return 'Clean up';
    }

    public function estimate(): int
    {
        return 1;
    }

    public function advance(): int
    {
        return 1;
    }

    public function completed(): bool
    {
        return true;
    }
}
