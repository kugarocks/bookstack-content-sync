<?php

namespace Tests\Unit\Support\BookStack;

use Kugarocks\BookStackContentSync\Support\BookStack\HostVersionGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HostVersionGuardTest extends TestCase
{
    public function test_prefers_bookstack_app_version_when_available(): void
    {
        $guard = new class extends HostVersionGuard
        {
            protected function detectAppVersion(): ?string
            {
                return 'v26.03.1';
            }

            protected function detectVersionFileVersion(): ?string
            {
                return 'v26.02.9';
            }

            protected function detectComposerRootVersion(): ?string
            {
                return 'dev-sync';
            }
        };

        $guard->ensureSupportedVersion();

        $this->addToAssertionCount(1);
    }

    public function test_uses_version_file_when_app_version_is_unavailable(): void
    {
        $guard = new class extends HostVersionGuard
        {
            protected function detectAppVersion(): ?string
            {
                return null;
            }

            protected function detectVersionFileVersion(): ?string
            {
                return 'v26.03.3';
            }

            protected function detectComposerRootVersion(): ?string
            {
                return 'dev-sync';
            }
        };

        $guard->ensureSupportedVersion();

        $this->addToAssertionCount(1);
    }

    public function test_falls_back_to_composer_root_version_when_other_sources_are_unavailable(): void
    {
        $guard = new class extends HostVersionGuard
        {
            protected function detectAppVersion(): ?string
            {
                return null;
            }

            protected function detectVersionFileVersion(): ?string
            {
                return null;
            }

            protected function detectComposerRootVersion(): ?string
            {
                return 'v26.03.2';
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
