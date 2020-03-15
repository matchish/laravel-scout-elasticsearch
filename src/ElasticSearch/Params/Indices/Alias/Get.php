<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias;

/**
 * @internal
 */
final class Get
{
    /**
     * @var string
     */
    private $alias;
    /**
     * @var string
     */
    private $index;

    /**
     * Get constructor.
     * @param string $alias
     * @param string $index
     */
    public function __construct(string $alias, string $index = '*')
    {
        $this->alias = $alias;
        $this->index = $index;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'index' => $this->index,
            'name' => $this->alias,
        ];
    }

    public static function anyIndex(string $alias): Get
    {
        return new static($alias, '*');
    }
}
