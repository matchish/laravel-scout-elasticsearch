<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @internal
 */
final class Index
{
    /**
     * @var array
     */
    private $aliases = [];

    /**
     * @var string
     */
    private $name;
    /**
     * @var array|null
     */
    private $settings;
    /**
     * @var array|null
     */
    private $mappings;

    /**
     * Index constructor.
     *
     * @param  string  $name
     * @param  array  $settings
     * @param  array  $mappings
     */
    public function __construct(string $name, array $settings = null, array $mappings = null)
    {
        $this->name = $name;
        $this->settings = $settings;
        $this->mappings = $mappings;
    }

    /**
     * @return array
     */
    public function aliases(): array
    {
        return $this->aliases;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @param  Alias  $alias
     */
    public function addAlias(Alias $alias): void
    {
        $this->aliases[$alias->name()] = $alias->config() ?: new \stdClass();
    }

    /**
     * @return array
     */
    public function config(): array
    {
        $config = [];
        if (! empty($this->settings)) {
            $config['settings'] = $this->settings;
        }
        if (! empty($this->mappings)) {
            $config['mappings'] = $this->mappings;
        }
        if (! empty($this->aliases())) {
            $config['aliases'] = $this->aliases();
        }

        return $config;
    }

    public static function fromSource(ImportSource $source): Index
    {
        $name = $source->searchableAs().'_'.time();
        $settingsConfigKey = "elasticsearch.indices.settings.{$source->searchableAs()}";
        $mappingsConfigKey = "elasticsearch.indices.mappings.{$source->searchableAs()}";
        $defaultSettings = [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,

        ];
        $settings = config($settingsConfigKey, config('elasticsearch.indices.settings.default', $defaultSettings));
        $mappings = config($mappingsConfigKey, config('elasticsearch.indices.mappings.default'));

        return new static($name, $settings, $mappings);
    }
}
