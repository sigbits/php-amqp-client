<?php

declare(strict_types=1);

namespace Sigbits\Amqp\Tests;

use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    private const PHP_REQUIREMENT = '>=8.3.36 <8.4 || >=8.4.27 <8.5 || >=8.5.12 <8.6';

    public function testProjectRunsOnSupportedPhpPatchVersion(): void
    {
        self::assertTrue(
            $this->isSupportedPhpVersion(PHP_VERSION),
            sprintf('PHP %s is below the supported patch floor.', PHP_VERSION),
        );
    }

    public function testComposerRequirementRejectsAffectedPhpPatchVersions(): void
    {
        $composerJson = json_decode((string) file_get_contents(__DIR__ . '/../composer.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(self::PHP_REQUIREMENT, $composerJson['require']['php']);

        self::assertFalse($this->isSupportedPhpVersion('8.2.35'));
        self::assertFalse($this->isSupportedPhpVersion('8.3.35'));
        self::assertFalse($this->isSupportedPhpVersion('8.4.26'));
        self::assertFalse($this->isSupportedPhpVersion('8.5.11'));

        self::assertTrue($this->isSupportedPhpVersion('8.3.36'));
        self::assertTrue($this->isSupportedPhpVersion('8.4.27'));
        self::assertTrue($this->isSupportedPhpVersion('8.5.12'));
    }

    private function isSupportedPhpVersion(string $version): bool
    {
        $versionId = $this->versionId($version);

        return $versionId >= 80336 && $versionId < 80400
            || $versionId >= 80427 && $versionId < 80500
            || $versionId >= 80512 && $versionId < 80600;
    }

    private function versionId(string $version): int
    {
        [$major, $minor, $patch] = array_map('intval', explode('.', $version));

        return $major * 10000 + $minor * 100 + $patch;
    }
}
