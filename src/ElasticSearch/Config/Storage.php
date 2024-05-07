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
        /** @var mixed $hostConfig */
        $hostConfig = $this->loadConfig('host');

        if (is_string($hostConfig)) {
            /** @var string $hostConfig */
            return explode(',', $hostConfig);
        }

        return [];
    }

    /**
     * @return ?string
     */
    public function user(): ?string
    {
        /** @var string|null $userConfig */
        $userConfig = $this->loadConfig('user');

        return $userConfig;
    }

    /**
     * @return ?string
     */
    public function password(): ?string
    {
        /** @var string|null $passwordConfig */
        $passwordConfig = $this->loadConfig('password');

        return $passwordConfig;
    }

    /**
     * @return ?string
     */
    public function elasticCloudId(): ?string
    {
        /** @var string|null $cloudConfig */
        $cloudConfig = $this->loadConfig('cloud_id');

        return $cloudConfig;
    }

    /**
     * @return ?string
     */
    public function apiKey(): ?string
    {
        /** @var string|null $apiConfig */
        $apiConfig = $this->loadConfig('api_key');

        return $apiConfig;
    }

    /**
     * @return ?int
     */
    public function queueTimeout(): ?int
    {
        $queueTimeoutConfig = $this->loadConfig('queue.timeout');

        if (is_numeric($queueTimeoutConfig)) {
            return intval($queueTimeoutConfig);
        }
        
        return null;
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
