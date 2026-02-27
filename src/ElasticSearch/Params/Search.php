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
     * @var array<mixed>
     */
    private $body;

    /**
     * @param  string  $index
     * @param  array<mixed>  $body
     */
    public function __construct(string $index, array $body)
    {
        $this->index = $index;
        $this->body = $body;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'body' => $this->body,
        ];
    }
}
