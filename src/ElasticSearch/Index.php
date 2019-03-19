<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 17.03.19
 * Time: 7:26
 */

namespace Matchish\ScoutElasticSearch\ElasticSearch;


/**
 * @internal
 */
final class Index
{
    private $aliases = [];
    private $name;
    private $settings;
    private $mappings;

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
        $this->aliases[$alias->name()] =  $alias->config() ?: new \stdClass();
    }

    public function config(): array
    {
        $config = [
            'settings' => [
                "number_of_shards" => 1,
                "number_of_replicas" => 0,
                'analysis' => [
                    'analyzer' => [
                        'default' => [
                            'type' => "custom",
                            'tokenizer' => "standard",
                            "char_filter" => ["html_strip"],
                            'filter' => ["standard", "lowercase", "asciifolding", "matchish_index_shingle", "matchish_stemmer",]
                        ],
                        'instantsearch_index' => [
                            'type' => "custom",
                            'tokenizer' => "standard",
                            "char_filter" => ["html_strip"],
                            'filter' => ["lowercase", "asciifolding", "synonym", "matchish_edge_ngram"]
                        ],
                        'matchish_search' => [
                            'type' => "custom",
                            'tokenizer' => "standard",
                            'filter' => ["standard", "lowercase", "asciifolding", "synonym", "matchish_stemmer",]
                        ],
                        'matchish_word_search' => [
                            'type' => "custom",
                            'tokenizer' => "standard",
                            'filter' => ["lowercase", "asciifolding", "synonym"]
                        ],
                    ],
                    'filter' => [
                        'matchish_index_shingle' => [
                            'type' => "shingle",
                            'token_separator' => ""
                        ],
                        'matchish_edge_ngram' => [
                            'type' => "edgeNGram",
                            'min_gram' => 1,
                            'max_gram' => 50
                        ],
                        'matchish_stemmer' => [
                            'type' => "snowball",
                            'language' => "Russian"
                        ],
                        "synonym" => [
                            "type" => "synonym",
                            "synonyms" => [
                                'зарядка => зарядное устройство',
                                'мобильник, мобила, труба => смартфон'
                            ]
                        ]
                    ]
                ],
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
