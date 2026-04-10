<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

use InvalidArgumentException;

class SyncConfigEnvCredentialResolver
{
    /**
     * @return array{tokenId: string, tokenSecret: string}
     */
    public function resolve(SyncConfig $config): array
    {
        return [
            'tokenId' => $this->requireEnv($config->tokenIdEnvVar),
            'tokenSecret' => $this->requireEnv($config->tokenSecretEnvVar),
        ];
    }

    protected function requireEnv(string $key): string
    {
        $value = $_SERVER[$key] ?? getenv($key) ?: null;

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Environment variable [{$key}] must be set");
        }

        return $value;
    }
}
