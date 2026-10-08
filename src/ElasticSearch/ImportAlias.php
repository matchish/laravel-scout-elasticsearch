<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

/**
 * The revocable write capability of one import. Range and catch-up
 * jobs write through this alias with require_alias, so once the alias
 * is gone — the import was superseded, or it published — a late write
 * is rejected by Elasticsearch instead of auto-creating the index it
 * targets.
 *
 * @internal
 */
final class ImportAlias implements Alias
{
    /**
     * @var string
     */
    private $indexName;

    /**
     * @param  string  $indexName
     */
    public function __construct(string $indexName)
    {
        $this->indexName = $indexName;
    }

    public static function of(string $indexName): string
    {
        return $indexName.'-import';
    }

    public function name(): string
    {
        return self::of($this->indexName);
    }

    /**
     * @return array<mixed>
     */
    public function config(): array
    {
        return [];
    }
}
