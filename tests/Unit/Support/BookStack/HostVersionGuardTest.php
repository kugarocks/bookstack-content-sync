<?php

namespace Tests\Unit\Support\BookStack;

use Kugarocks\BookStackContentSync\Support\BookStack\HostVersionGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HostVersionGuardTest extends TestCase
{
    public function test_accepts_supported_release_versions(): void
    {
        $guard = new class extends HostVersionGuard
        {
            public function detectVersion(): ?string
            {
                return 'v26.03.1';
            }
        };

        $guard->ensureSupportedVersion();

        $this->addToAssertionCount(1);
    }

    public function test_rejects_versions_below_minimum(): void
    {
        $guard = new class extends HostVersionGuard
        {
            public function detectVersion(): ?string
            {
                return 'v26.02';
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires BookStack >= 26.03; detected v26.02');

        $guard->ensureSupportedVersion();
    }

    public function test_rejects_undetectable_versions(): void
    {
        $guard = new class extends HostVersionGuard
        {
            public function detectVersion(): ?string
            {
                return null;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to determine the host BookStack version');

        $guard->ensureSupportedVersion();
    }

    public function test_rejects_non_release_versions(): void
    {
        $guard = new HostVersionGuard();

        $this->assertFalse($guard->isSupported('dev-main'));
        $this->assertFalse($guard->isSupported('release-branch'));
    }
}
