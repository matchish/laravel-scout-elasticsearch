<?php
/**
 * Created by PhpStorm.
 * User: matchish
 * Date: 09.03.19
 * Time: 13:55
 */

namespace Matchish\ScoutElasticSearch\Jobs;


use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;

/**
 * @internal
 */
final class CreateWriteIndex implements ShouldQueue
{
    use Queueable;
    /**
     * @var Index
     */
    private $index;

    /**
     * CreateWriteIndex constructor.
     * @param Index $index
     */
    public function __construct(Index $index)
    {
        $this->index = $index;
    }

    /**
     * @param Client $elasticsearch
     */
    public function handle(Client $elasticsearch): void
    {
        try {
            $response = $elasticsearch->indices()->getAliases(['index' => '*', 'name' => $this->index->alias()]);
        } catch (Missing404Exception $e) {
            $response = [];
        }
        //Write alias can be only one
        $actions = [['add' => ['index' => $this->index->name(), 'is_write_index' => true, 'alias' => $this->index->alias()]]];
        foreach ($response as $index => $alias) {
            $actions[] = ['add' => ['index' => $index, 'is_write_index' => false, 'alias' => $this->index->alias()]];
        }
        $elasticsearch->indices()->create([
            'index' => $this->index->name(),
            'body' => $this->getIndexConfig()
        ]);
        $elasticsearch->indices()->updateAliases([
            'body' => [
                'actions' => $actions
            ]
        ]);
    }

    /**
     * @return array
     */
    private function getIndexConfig()
    {
        return [
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
    }

}
