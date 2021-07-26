<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use ONGR\ElasticsearchDSL\BuilderInterface;

class EmptyQuery implements BuilderInterface
{
    /**
     * Generates array which will be passed to elasticsearch-php client.
     *
     * @return array
     */
    public function toArray()
    {
        return [];
    }

    /**
     * Returns element type.
     *
     * @return string
     */
    public function getType()
    {
        return '';
    }
}