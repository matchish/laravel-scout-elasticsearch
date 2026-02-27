<?php

namespace Matchish\ScoutElasticSearch\Contracts;

interface ElasticParamsContract
{
    /**
     * Set elasticsearch score.
     *
     * @param  float  $score
     * @return void
     */
    public function setElasticsearchScore(float $score): void;

    /**
     * Get elasticsearch score.
     *
     * @return float|null
     */
    public function getElasticsearchScore(): ?float;

    /**
     * Set elasticsearch highlighting.
     *
     * @param  array<string, array<mixed>|string>  $highlight
     * @return void
     */
    public function setElasticsearchHighlight(array $highlight): void;

    /**
     * Get elasticsearch highlighting.
     *
     * @return array<string, array<mixed>|string>|null
     */
    public function getElasticsearchHighlight(): ?array;
}
