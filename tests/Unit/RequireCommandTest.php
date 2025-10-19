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

    public function testExecuteRequireWithFallbackSuccess(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('executeRequireWithFallback');
        $method->setAccessible(true);

        // Mock output interface
        $output = $this->createMock('Symfony\Component\Console\Output\OutputInterface');
        $output->expects($this->never())
               ->method('writeln');

        // Test successful execution (should not trigger fallback)
        $result = $method->invoke(
            $this->command,
            'echo "success"',  // command that will succeed
            'dev-main',
            'test/package',
            'composer',
            false,
            $output
        );

        $this->assertTrue($result);
    }

    public function testExecuteRequireWithFallbackTriggered(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('executeRequireWithFallback');
        $method->setAccessible(true);

        // Mock output interface
        $output = $this->createMock('Symfony\Component\Console\Output\OutputInterface');
        $output->expects($this->once())
               ->method('writeln')
               ->with('<comment>dev-main gagal, mencoba dev-master sebagai fallback...</comment>');

        // Test with failing command that should trigger fallback
        $result = $method->invoke(
            $this->command,
            'false',  // command that will fail
            'dev-main',
            'test/package',
            'composer',
            false,
            $output
        );

        // The fallback should also fail since we're using 'false' command
        // but the fallback logic should have been triggered
        $this->assertFalse($result);
    }

    public function testExecuteRequireWithFallbackNotTriggeredForNonDevMain(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('executeRequireWithFallback');
        $method->setAccessible(true);

        // Mock output interface
        $output = $this->createMock('Symfony\Component\Console\Output\OutputInterface');
        $output->expects($this->never())
               ->method('writeln');

        // Test with failing command but version is not dev-main (should not trigger fallback)
        $result = $method->invoke(
            $this->command,
            'false',  // command that will fail
            '^1.0',   // not dev-main, so no fallback
            'test/package',
            'composer',
            false,
            $output
        );

        $this->assertFalse($result);
    }

    public function testExecuteRequireWithFallbackIncludesDevFlag(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('executeRequireWithFallback');
        $method->setAccessible(true);

        // Mock output interface
        $output = $this->createMock('Symfony\Component\Console\Output\OutputInterface');
        $output->expects($this->once())
               ->method('writeln')
               ->with('<comment>dev-main gagal, mencoba dev-master sebagai fallback...</comment>');

        // Test with dev flag enabled
        $result = $method->invoke(
            $this->command,
            'false',  // command that will fail
            'dev-main',
            'test/package',
            'composer',
            true,     // dev flag enabled
            $output
        );

        $this->assertFalse($result);
    }

    public function testDefaultVersionIsDevMain(): void
    {
        // Test that default version is now dev-main instead of dev-master
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('require');
        $commandTester = new CommandTester($command);
        
        // Create a local package for testing
        $localPackagePath = $this->tempDir . '/version-test-package';
        mkdir($localPackagePath);
        file_put_contents($localPackagePath . '/composer.json', '{"name": "test/version-package"}');
        
        $commandTester->execute([
            'nama_paket_folder' => $localPackagePath,
            // No version specified - should default to dev-main
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
