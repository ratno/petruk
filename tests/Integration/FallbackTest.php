<?php

namespace Ratno\Petruk\Test\Integration;

use PHPUnit\Framework\TestCase;
use Ratno\Petruk\Console\RequireCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class FallbackTest extends TestCase
{
    private $tempDir;
    private $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->originalCwd = getcwd();
        $this->tempDir = sys_get_temp_dir() . '/petruk-fallback-test-' . uniqid();
        
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->tempDir);
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        
        $filesystem = new Filesystem();
        if ($filesystem->exists($this->tempDir)) {
            $filesystem->remove($this->tempDir);
        }
        
        parent::tearDown();
    }

    public function testFallbackBehaviorIntegration(): void
    {
        // Create local test packages to avoid real network calls
        $localPackagePath = $this->tempDir . '/test-package';
        mkdir($localPackagePath);
        file_put_contents($localPackagePath . '/composer.json', '{
            "name": "test/fallback-package",
            "description": "Test package for fallback behavior"
        }');
        
        // Set up the command
        $application = new Application();
        $command = new RequireCommand();
        $application->add($command);
        
        $commandTester = new CommandTester($command);
        
        // Execute command with local package (this should work without fallback)
        $commandTester->execute([
            'nama_paket_folder' => $localPackagePath
        ]);
        
        $output = $commandTester->getDisplay();
        
        // Local packages should work fine
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
    }

    public function testLocalPackageInstallation(): void
    {
        // Create another local test package
        $localPackagePath = $this->tempDir . '/another-package';
        mkdir($localPackagePath);
        file_put_contents($localPackagePath . '/composer.json', '{
            "name": "test/another-package",
            "description": "Another test package"
        }');
        
        // Set up the command
        $application = new Application();
        $command = new RequireCommand();
        $application->add($command);
        
        $commandTester = new CommandTester($command);
        
        // Execute command with specific version
        $commandTester->execute([
            'nama_paket_folder' => $localPackagePath,
            'versi' => '^1.0'
        ]);
        
        $output = $commandTester->getDisplay();
        
        // Should complete successfully
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
    }

    public function testCustomPackageNameOption(): void
    {
        // Create local test package with custom name override
        $localPackagePath = $this->tempDir . '/custom-package';
        mkdir($localPackagePath);
        file_put_contents($localPackagePath . '/composer.json', '{
            "name": "original/package-name",
            "description": "Package with original name"
        }');
        
        // Set up the command
        $application = new Application();
        $command = new RequireCommand();
        $application->add($command);
        
        $commandTester = new CommandTester($command);
        
        // Execute command with custom package name
        $commandTester->execute([
            'nama_paket_folder' => $localPackagePath,
            '--paket' => 'custom/overridden-name'
        ]);
        
        $output = $commandTester->getDisplay();
        
        // Should complete successfully
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
    }
}