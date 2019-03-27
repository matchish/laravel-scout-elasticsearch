<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

/**
 * @internal
 */
final class DefaultAlias implements Alias
{
    /**
     * @var string
     */
    private $name;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function config(): array
    {
        return [];
    }
}
