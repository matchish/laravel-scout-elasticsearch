<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch;

use Matchish\ScoutElasticSearch\Searchable\ImportSource;

/**
 * @internal
 */
final class Index
{
    /**
     * @var array<string, mixed>
     */
    private $aliases = [];

    /**
     * @var string
     */
    private $name;
    /**
     * @var array<mixed>|null
     */
    private $settings;
    /**
     * @var array<mixed>|null
     */
    private $mappings;

    /**
     * Index constructor.
     *
     * @param  string  $name
     * @param  array<mixed>  $settings
     * @param  array<mixed>  $mappings
     */
    public function __construct(string $name, ?array $settings = null, ?array $mappings = null)
    {
        $this->name = $name;
        $this->settings = $settings;
        $this->mappings = $mappings;
    }

    /**
     * @return array<string, mixed>
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
     * @return array<mixed>
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
        // A delete leaves a versioned tombstone that keeps rejecting
        // older writes, but only while Elasticsearch retains it. The
        // default is 60s, which a slow import job can outlive.
        $gcDeletes = config('elasticsearch.indices.gc_deletes', '12h');
        /** @var array<string, mixed> $settings */
        $settings = config($settingsConfigKey, config('elasticsearch.indices.settings.default', $defaultSettings));
        if ($gcDeletes !== null && ! isset($settings['index.gc_deletes'], $settings['gc_deletes'])) {
            $settings['index.gc_deletes'] = $gcDeletes;
        }
        /** @var array<string, mixed> $mappings */
        $mappings = config($mappingsConfigKey, config('elasticsearch.indices.mappings.default')) ?: [];

        // Provenance marker: proves the index was created by this
        // package, so cleanup may reclaim it once nothing routes to
        // it. Cleanup never touches unmarked indices.
        $meta = [];
        if (isset($mappings['_meta']) && is_array($mappings['_meta'])) {
            $meta = $mappings['_meta'];
        }
        $mappings['_meta'] = array_merge($meta, ['scout_import' => true]);

        return new static($name, $settings, $mappings);
    }
}
