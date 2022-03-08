<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Params;

/**
 * @internal
 */
final class Search
{
    /**
     * @var string
     */
    private $index;
    /**
     * @var array
     */
    private $body;

    /**
     * @param  string  $index
     * @param  array  $body
     */
    public function __construct(string $index, array $body)
    {
        $this->index = $index;
        $this->body = $body;
    }

    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'body' => $this->body,
        ];
    }
}
