<?php

namespace Ratno\Petruk\Test\Unit;

use PHPUnit\Framework\TestCase;
use Ratno\Petruk\Console\RequireCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class RequireCommandTest extends TestCase
{
    private RequireCommand $command;
    private string $tempDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->command = new RequireCommand();
        $this->originalCwd = getcwd();
        $this->tempDir = sys_get_temp_dir() . '/petruk_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $filesystem = new Filesystem();
        if (is_dir($this->tempDir)) {
            $filesystem->remove($this->tempDir);
        }
    }

    public function testCommandConfiguration(): void
    {
        $this->assertEquals('require', $this->command->getName());
        $this->assertEquals('Composer require khusus untuk private repo', $this->command->getDescription());
        
        $this->assertTrue($this->command->getDefinition()->hasArgument('nama_paket_folder'));
        $this->assertTrue($this->command->getDefinition()->hasArgument('versi'));
        
        $this->assertTrue($this->command->getDefinition()->hasOption('dev'));
        $this->assertTrue($this->command->getDefinition()->hasOption('global'));
        $this->assertTrue($this->command->getDefinition()->hasOption('paket'));
    }

    public function testCreateComposerJsonFileIfNotExists(): void
    {
        $composerFile = $this->tempDir . '/composer.json';
        
        // Ensure file doesn't exist initially
        $this->assertFileDoesNotExist($composerFile);
        
        // Use reflection to call protected method
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('createComposerJsonFileIfNotExists');
        $method->setAccessible(true);
        $method->invoke($this->command);
        
        // Check if file was created
        $this->assertFileExists($composerFile);
        $this->assertEquals('{}', file_get_contents($composerFile));
    }

    public function testCreateComposerJsonFileWhenExists(): void
    {
        $composerFile = $this->tempDir . '/composer.json';
        $existingContent = '{"name": "test/package"}';
        file_put_contents($composerFile, $existingContent);
        
        // Use reflection to call protected method
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('createComposerJsonFileIfNotExists');
        $method->setAccessible(true);
        $method->invoke($this->command);
        
        // Content should remain unchanged
        $this->assertEquals($existingContent, file_get_contents($composerFile));
    }

    public function testCheckIfNamaPaketFolderIsLocalFolder(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('checkIfNamaPaketFolderIsLocalFolder');
        $method->setAccessible(true);

        // Test non-existent folder
        $result = $method->invoke($this->command, '/nonexistent/folder');
        $this->assertFalse($result);

        // Test folder without composer.json
        $folderPath = $this->tempDir . '/test_folder';
        mkdir($folderPath);
        $result = $method->invoke($this->command, $folderPath);
        $this->assertFalse($result);

        // Test folder with composer.json but no name field
        $composerContent = '{"description": "test package"}';
        file_put_contents($folderPath . '/composer.json', $composerContent);
        $result = $method->invoke($this->command, $folderPath);
        $this->assertFalse($result);

        // Test folder with valid composer.json
        $composerContent = '{"name": "vendor/package-name", "description": "test package"}';
        file_put_contents($folderPath . '/composer.json', $composerContent);
        $result = $method->invoke($this->command, $folderPath);
        $this->assertEquals('vendor/package-name', $result);
    }

    public function testExecuteWithInvalidSshFormat(): void
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('require');
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            'nama_paket_folder' => 'invalid-ssh-format',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Nama Remote Repo harus dalam format:', $output);
        $this->assertStringContainsString('git.server-repo.com:nama/paket', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testExecuteWithLocalFolder(): void
    {
        // Create a test local package folder
        $localPackagePath = $this->tempDir . '/local-package';
        mkdir($localPackagePath);
        file_put_contents($localPackagePath . '/composer.json', '{"name": "local/test-package"}');

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('require');
        $commandTester = new CommandTester($command);
        
        // Mock composer command to prevent actual execution
        $commandTester->execute([
            'nama_paket_folder' => $localPackagePath,
            'versi' => 'dev-main'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
    }

    public function testExecuteWithDevOption(): void
    {
        $localPackagePath = $this->tempDir . '/dev-package';
        mkdir($localPackagePath);
        file_put_contents($localPackagePath . '/composer.json', '{"name": "local/dev-package"}');

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('require');
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            'nama_paket_folder' => $localPackagePath,
            '--dev' => true
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
    }

    public function testExecuteWithGlobalOption(): void
    {
        $localPackagePath = $this->tempDir . '/global-package';
        mkdir($localPackagePath);
        file_put_contents($localPackagePath . '/composer.json', '{"name": "local/global-package"}');

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('require');
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            'nama_paket_folder' => $localPackagePath,
            '--global' => true
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
    }

    public function testExecuteWithCustomPacketName(): void
    {
        $localPackagePath = $this->tempDir . '/custom-package';
        mkdir($localPackagePath);
        file_put_contents($localPackagePath . '/composer.json', '{"name": "local/original-name"}');

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('require');
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            'nama_paket_folder' => $localPackagePath,
            '--paket' => 'custom/package-name'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
    }
}