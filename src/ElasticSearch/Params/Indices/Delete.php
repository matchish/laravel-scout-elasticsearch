<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices;

/**
 * @internal
 */
final class Delete
{
    /**
     * @var string
     */
    private $index;

    /**
     * Delete constructor.
     *
     * @param  string  $index
     */
    public function __construct(string $index)
    {
        $this->index = $index;
    }

    /**
     * @return array<string, string>
     */
    public function toArray()
    {
        return [
            'index' => $this->index,
        ];
    }
}
