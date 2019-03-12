<?php
namespace Matchish\ScoutElasticSearch\ElasticSearch;

/**
 * @internal
 */
class Index
{
    /**
     * @var string
     */
    private $alias;

    /**
     * @var string
     */
    private $name;

    /**
     * Index constructor.
     * @param string $alias
     */
    public function __construct(string $alias, string $name = null)
    {
        $this->alias = $alias;
        $this->name = $name ?: $this->alias . '_' . time();
    }

    /**
     * @return string
     */
    public function alias():string
    {
        return $this->alias;
    }

    /**
     * @return string
     */
    public function name():string
    {
        return $this->name;
    }
}
