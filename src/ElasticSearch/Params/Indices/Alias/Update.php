<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Alias;

/**
 * @internal
 */
final class Update
{
    /**
     * @var array
     */
    private $actions = [];

    /**
     * @param array $actions
     */
    public function __construct(array $actions = [])
    {
        $this->actions = $actions;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'body' => [
                'actions' => $this->actions,
            ],
        ];
    }

    public function add(string $index, string $alias): void
    {
        $this->actions[] = ['add' => [
            'index' => $index,
            'alias' => $alias,
        ]];
    }

    public function removeIndex(string $index): void
    {
        $this->actions[] = ['remove_index' => ['index' => $index]];
    }
}
