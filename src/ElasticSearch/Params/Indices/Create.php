<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices;

/**
 * @internal
 */
final class Create
{
    /**
     * @var string
     */
    private $index;
    /**
     * @var array<mixed>
     */
    private $config;

    /**
     * Create constructor.
     *
     * @param  string  $index
     * @param  array<mixed>  $config
     */
    public function __construct(string $index, array $config)
    {
        $this->index = $index;
        $this->config = $config;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'body' => $this->config,
        ];
    }
}
