<?php

namespace Matchish\ScoutElasticSearch\Traits;

trait ElasticParams
{
    /**
     * @var float|null
     */
    private ?float $_score = null;

    /**
     * @var array<string, array|string>|null
     */
    private ?array $_highlight = null;

    /**
     * Set elasticsearch score.
     *
     * @param  float  $score
     * @return void
     */
    public function setElasticsearchScore(float $score): void
    {
        $this->_score = $score;
    }

    /**
     * Get elasticsearch score.
     *
     * @return float|null
     */
    public function getElasticsearchScore(): ?float
    {
        return $this->_score;
    }

    /**
     * Set elasticsearch highlighting.
     *
     * @param array<string, array|string>
     * @return void
     */
    public function setElasticsearchHighlight(array $highlight): void
    {
        $this->_highlight = $highlight;
    }

    /**
     * Get elasticsearch highlighting.
     *
     * @return array<string, array|string>|null
     */
    public function getElasticsearchHighlight(): ?array
    {
        return $this->_highlight;
    }
}
