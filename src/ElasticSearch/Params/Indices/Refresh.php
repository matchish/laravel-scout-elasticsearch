<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices;

/**
 * @internal
 */
final class Refresh
{
    /**
     * @var string
     */
    private $index;

    /**
     * Refresh constructor.
     *
     * @param  string  $index
     */
    public function __construct(string $index)
    {
        $this->index = $index;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'index' => $this->index,
        ];
    }
}
