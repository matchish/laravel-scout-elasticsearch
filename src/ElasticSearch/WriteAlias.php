<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

/**
 * @internal
 */
final class WriteAlias implements Alias
{
    /**
     * @var Alias
     */
    private $origin;

    /**
     * @param Alias $origin
     */
    public function __construct(Alias $origin)
    {
        $this->origin = $origin;
    }

    public function name(): string
    {
        return $this->origin->name();
    }

    public function config(): array
    {
        return array_merge($this->origin->config(), ['is_write_index' => true]);
    }
}
