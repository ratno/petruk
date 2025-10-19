<?php

namespace Ratno\Petruk\Test;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Filesystem\Filesystem;

abstract class TestCase extends BaseTestCase
{
    protected string $tempDir;
    protected string $originalCwd;
    protected Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->filesystem = new Filesystem();
        $this->originalCwd = getcwd();
        $this->tempDir = $this->createTempDirectory();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
        
        parent::tearDown();
    }

    protected function createTempDirectory(): string
    {
        $tempDir = sys_get_temp_dir() . '/petruk_test_' . uniqid();
        $this->filesystem->mkdir($tempDir, 0777);
        return $tempDir;
    }

    protected function createLocalPackage(string $name, string $packageName = null, array $additionalConfig = []): string
    {
        $packageDir = $this->tempDir . '/' . $name;
        $this->filesystem->mkdir($packageDir, 0777);
        
        $composerConfig = array_merge([
            'name' => $packageName ?: "local/{$name}",
            'type' => 'library',
            'description' => "Test package {$name}",
        ], $additionalConfig);
        
        $this->filesystem->dumpFile(
            $packageDir . '/composer.json',
            json_encode($composerConfig, JSON_PRETTY_PRINT)
        );
        
        return $packageDir;
    }

    protected function assertComposerJsonExists(): void
    {
        $composerFile = $this->tempDir . '/composer.json';
        $this->assertFileExists($composerFile);
        
        $content = file_get_contents($composerFile);
        $this->assertJson($content);
    }

    protected function mockEnvironmentVariable(string $name, string $value): void
    {
        $_SERVER[$name] = $value;
        putenv("{$name}={$value}");
    }

    protected function restoreEnvironmentVariable(string $name): void
    {
        if (isset($_SERVER[$name])) {
            unset($_SERVER[$name]);
        }
        putenv($name);
    }
}