<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;


/**
 * @internal
 */
final class Index
{
    private $aliases = [];
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function aliases()
    {
        return $this->aliases;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function addAlias(Alias $alias): void
    {
        $this->aliases[$alias->name()] = $alias->config() ?: new \stdClass();
    }

    public function config(): array
    {
        $config = [
            'settings' => [
                "number_of_shards" => 1,
                "number_of_replicas" => 0,
            ],
            "mappings" => [
                "_doc" => [
                    "properties" => [
                        "type" => [
                            "type" => "keyword"
                        ],
                        "title" => [
                            "type" => "text",
                            "copy_to" => "searchable"
                        ],
                        "description" => [
                            "type" => "text",
                            "copy_to" => "searchable"
                        ],
                        "searchable" => [
                            "type" => "text"
                        ],
                        "price" => [
                            "type" => "integer"
                        ]
                    ]
                ]
            ]
        ];
        if ($this->aliases()) {
            $config['aliases'] = $this->aliases();
        }
        return $config;
    }

    public static function fromSearchable($searchable)
    {
        $name = $searchable->searchableAs() . '_' . time();
        return new static($name);
    }

}
