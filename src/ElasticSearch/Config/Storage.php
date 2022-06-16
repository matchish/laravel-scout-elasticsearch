<?php

namespace Matchish\ScoutElasticSearch\ElasticSearch\Config;

class Storage
{
    protected string $config;

    /**
     * @param  string  $config
     */
    private function __construct(string $config)
    {
        $this->config = $config;
    }

    /**
     * @param  string  $config
     * @return Storage
     */
    public static function load(string $config): Storage
    {
        return new self($config);
    }

    /**
     * @return array
     */
    public function hosts(): array
    {
        return explode(',', $this->loadConfig('host'));
    }

    /**
     * @return ?string
     */
    public function user(): ?string
    {
        return $this->loadConfig('user');
    }

    /**
     * @return ?string
     */
    public function password(): ?string
    {
        return $this->loadConfig('password');
    }

    /**
     * @return ?string
     */
    public function elasticCloudId(): ?string
    {
        return $this->loadConfig('cloud_id');
    }

    /**
     * @return ?string
     */
    public function apiKey(): ?string
    {
        return $this->loadConfig('api_key');
    }

    /**
     * @param  string  $path
     * @return mixed
     */
    private function loadConfig(string $path): mixed
    {
        return config($this->getKey($path));
    }

    /**
     * @param  string  $path
     * @return string
     */
    private function getKey(string $path): string
    {
        return sprintf('%s.%s', $this->config, $path);
    }
}
