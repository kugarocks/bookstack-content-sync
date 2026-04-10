<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

use InvalidArgumentException;
use JsonException;

class SyncConfigLoader
{
    public function load(string $path): SyncConfig
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Sync config file not found at path [{$path}]");
        }

        try {
            $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("Invalid JSON in sync config file [{$path}]", previous: $exception);
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Sync config must decode to a JSON object');
        }

        $version = $this->requireInt($data, 'version');
        $appUrl = $this->requireString($data, 'app_url');
        $contentPath = trim($this->requireString($data, 'content_path'), '/');
        $envVars = $this->requireArray($data, 'env_vars');

        if ($contentPath === '') {
            throw new InvalidArgumentException('Sync config field [content_path] cannot be empty');
        }

        return new SyncConfig(
            version: $version,
            appUrl: $appUrl,
            contentPath: $contentPath,
            tokenIdEnvVar: $this->requireString($envVars, 'token_id'),
            tokenSecretEnvVar: $this->requireString($envVars, 'token_secret'),
        );
    }

    protected function requireInt(array $data, string $key): int
    {
        if (!isset($data[$key]) || !is_int($data[$key])) {
            throw new InvalidArgumentException("Sync config field [{$key}] must be an integer");
        }

        return $data[$key];
    }

    protected function requireString(array $data, string $key): string
    {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
            throw new InvalidArgumentException("Sync config field [{$key}] must be a non-empty string");
        }

        return $data[$key];
    }

    protected function requireArray(array $data, string $key): array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            throw new InvalidArgumentException("Sync config field [{$key}] must be an object");
        }

        return $data[$key];
    }
}
