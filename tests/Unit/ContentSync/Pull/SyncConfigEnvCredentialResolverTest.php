<?php

namespace Tests\Unit\ContentSync\Pull;

use KugaRocks\BookStackContentSync\ContentSync\Pull\SyncConfigEnvCredentialResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SyncConfigEnvCredentialResolverTest extends TestCase
{
    public function test_resolves_token_values_from_environment()
    {
        $config = PullNodeFactory::config([
            'tokenIdEnvVar' => 'PULL_TOKEN_ID',
            'tokenSecretEnvVar' => 'PULL_TOKEN_SECRET',
        ]);

        $this->runWithEnv(['PULL_TOKEN_ID' => 'token-id', 'PULL_TOKEN_SECRET' => 'token-secret'], function () use ($config) {
            $credentials = (new SyncConfigEnvCredentialResolver())->resolve($config);

            $this->assertSame('token-id', $credentials['tokenId']);
            $this->assertSame('token-secret', $credentials['tokenSecret']);
        });
    }

    public function test_throws_if_required_environment_variable_missing()
    {
        $config = PullNodeFactory::config([
            'tokenIdEnvVar' => 'PULL_TOKEN_ID',
            'tokenSecretEnvVar' => 'PULL_TOKEN_SECRET',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PULL_TOKEN_SECRET');

        $this->runWithEnv(['PULL_TOKEN_ID' => 'token-id', 'PULL_TOKEN_SECRET' => null], function () use ($config) {
            (new SyncConfigEnvCredentialResolver())->resolve($config);
        });
    }

    protected function runWithEnv(array $valuesByKey, callable $callback): void
    {
        $originals = [];

        foreach ($valuesByKey as $key => $value) {
            $originals[$key] = $_SERVER[$key] ?? null;

            if ($value === null) {
                unset($_SERVER[$key]);
                putenv($key);
                continue;
            }

            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            $callback();
        } finally {
            foreach ($originals as $key => $value) {
                if ($value === null) {
                    unset($_SERVER[$key]);
                    putenv($key);
                    continue;
                }

                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}
