<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

/**
 * @internal
 */
final class FilteredAlias implements Alias
{
    /**
     * @var Alias
     */
    private Alias $origin;

    private Index $index;

    public function __construct(Alias $origin, Index $index)
    {
        $this->origin = $origin;
        $this->index = $index;
    }

    public function name(): string
    {
        return $this->origin->name();
    }

    public function config(): array
    {
        return array_merge($this->origin->config(), [
            'filter' => [
                'bool' => [
                    'must_not' => [
                        [
                            'term' => [
                                '_index' => $this->index->name(),
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
