<?php

namespace Matchish\ScoutElasticSearch\Jobs\Stages;

use Elastic\Elasticsearch\Client;
use Matchish\ScoutElasticSearch\Database\Scopes\FromScope;
use Matchish\ScoutElasticSearch\Database\Scopes\PageScope;
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

    public function handle(Client $elasticsearch = null): void
    {
        $this->handledChunks++;
        $results = $this->source->get()->filter->shouldBeSearchable();
        if (! $results->isEmpty()) {
            $results->first()->searchableUsing()->update($results);
            if ($results->first()->getKeyType() !== 'int') {
                $this->source->setChunkScope(new PageScope($this->handledChunks, $this->source->getChunkSize()));
            } else {
                $this->source->setChunkScope(new FromScope($results->last()->getKey(), $this->source->getChunkSize()));
            }
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
        if($source === null) {
            return null;
        }
        return new static($source);
    }
}
