<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Matchish\ScoutElasticSearch\Database\Scopes\FromScope;
use Matchish\ScoutElasticSearch\Database\Scopes\PageScope;
use Matchish\ScoutElasticSearch\ElasticSearch\BulkResult;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Bulk;
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
     * @var int
     */
    private $handledChunks = 0;

    /**
     * @param  ImportSource  $source
     */
    public function __construct(ImportSource $source)
    {
        $this->source = $source;
    }

    public function handle(?Client $elasticsearch = null): void
    {
        $this->handledChunks++;
        $models = $this->source->get();
        if ($models->isEmpty()) {
            return;
        }

        $searchable = $models->filter->shouldBeSearchable();
        if ($searchable->isNotEmpty()) {
            $this->flush($elasticsearch ?? app(Client::class), $searchable);
        }

        // Move past every row read, searchable or not. Moving only past
        // the searchable ones left a chunk with none of them in place,
        // so it was read again until the chunk count ran out, and every
        // row after it was skipped.
        /** @var Model $last */
        $last = $models->last();
        if ($last->getKeyType() !== 'int') {
            $this->source->setChunkScope(new PageScope($this->handledChunks, $this->source->getChunkSize()));
        } else {
            /** @var int $lastKey the key type is int in this branch */
            $lastKey = $last->getKey();
            $this->source->setChunkScope(new FromScope($lastKey, $this->source->getChunkSize()));
        }
    }

    /**
     * The rows were read before this write, so they go out as a snapshot:
     * a live change that reached the document since then must win, even
     * within the same second. The engine's update() would send them as
     * a live write instead.
     *
     * @param  EloquentCollection<int, Model>  $models
     */
    private function flush(Client $elasticsearch, EloquentCollection $models): void
    {
        $params = new Bulk(null, false, Bulk::WRITER_SNAPSHOT);
        $params->index($models->all());
        /** @var Elasticsearch $elasticResponse */
        $elasticResponse = $elasticsearch->bulk($params->toArray());
        $result = new BulkResult($elasticResponse->asArray());
        if ($result->hasFatalErrors()) {
            throw new \Exception('Bulk import error: '.$result->toJson());
        }
    }

    public function estimate(): int
    {
        return $this->source->getTotalChunks() + 1;
    }

    public function advance(): int
    {
        return 1;
    }

    public function title(): string
    {
        return 'Indexing...';
    }

    public function completed(): bool
    {
        return ($this->handledChunks - 1) >= $this->source->getTotalChunks();
    }

    /**
     * @param  ImportSource  $source
     * @return PullFromSource
     */
    public static function chunked(ImportSource $source): ?PullFromSource
    {
        $source = $source->chunked();
        if ($source === null) {
            return null;
        }

        return new static($source);
    }
}
