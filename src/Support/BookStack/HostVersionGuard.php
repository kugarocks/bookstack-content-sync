<?php

namespace Kugarocks\BookStackContentSync\Support\BookStack;

use Composer\InstalledVersions;
use RuntimeException;

class HostVersionGuard
{
    public const MINIMUM_SUPPORTED_VERSION = '26.03';

    public function ensureSupportedVersion(): void
    {
        $detectedVersion = $this->detectVersion();

        if ($detectedVersion === null) {
            throw new RuntimeException(sprintf(
                'Unable to determine the host BookStack version. This package requires BookStack >= %s.',
                self::MINIMUM_SUPPORTED_VERSION
            ));
        }

        if (!$this->isSupported($detectedVersion)) {
            throw new RuntimeException(sprintf(
                'bookstack-content-sync requires BookStack >= %s; detected %s.',
                self::MINIMUM_SUPPORTED_VERSION,
                $detectedVersion
            ));
        }
    }

    public function detectVersion(): ?string
    {
        $appVersion = $this->detectAppVersion();
        if ($appVersion !== null) {
            return $appVersion;
        }

        $versionFileVersion = $this->detectVersionFileVersion();
        if ($versionFileVersion !== null) {
            return $versionFileVersion;
        }

        return $this->detectComposerRootVersion();
    }

    public function isSupported(string $version): bool
    {
        $normalizedVersion = $this->normalizeVersion($version);
        if ($normalizedVersion === null) {
            return false;
        }

        return version_compare($normalizedVersion, self::MINIMUM_SUPPORTED_VERSION, '>=');
    }

    private function normalizeVersion(string $version): ?string
    {
        $version = ltrim(trim($version), 'vV');

        if (preg_match('/^\d+\.\d+(?:\.\d+)?(?:[-+][0-9A-Za-z.-]+)?$/', $version) !== 1) {
            return null;
        }

        return $version;
    }

    protected function detectAppVersion(): ?string
    {
        if (!class_exists(\BookStack\App\AppVersion::class)) {
            return null;
        }

        $version = \BookStack\App\AppVersion::get();

        return is_string($version) && trim($version) !== '' ? trim($version) : null;
    }

    protected function detectVersionFileVersion(): ?string
    {
        $versionFile = $this->resolveVersionFilePath();
        if ($versionFile === null || !is_file($versionFile) || !is_readable($versionFile)) {
            return null;
        }

        $version = trim((string) file_get_contents($versionFile));

        return $version !== '' ? $version : null;
    }

    protected function resolveVersionFilePath(): ?string
    {
        if (function_exists('base_path')) {
            return base_path('version');
        }

        return null;
    }

    protected function detectComposerRootVersion(): ?string
    {
        if (!class_exists(InstalledVersions::class)) {
            return null;
        }

        $rootPackage = InstalledVersions::getRootPackage();

        if (($rootPackage['name'] ?? null) !== 'bookstackapp/bookstack') {
            return null;
        }

        $version = $rootPackage['pretty_version'] ?? $rootPackage['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }
}
