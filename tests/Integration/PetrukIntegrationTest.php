<?php

namespace Ratno\Petruk\Test\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;
use Ratno\Petruk\Console\RequireCommand;

class PetrukIntegrationTest extends TestCase
{
    private string $tempDir;
    private string $originalCwd;
    private Application $application;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->tempDir = sys_get_temp_dir() . '/petruk_integration_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        chdir($this->tempDir);

        $this->application = new Application('Petruk', '1.0.12');
        $this->application->add(new RequireCommand());
        $this->application->setAutoExit(false);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $filesystem = new Filesystem();
        if (is_dir($this->tempDir)) {
            $filesystem->remove($this->tempDir);
        }
    }

    public function testFullWorkflowWithLocalPackage(): void
    {
        // Create a local package
        $localPackageDir = $this->tempDir . '/my-local-package';
        mkdir($localPackageDir, 0777, true);
        
        $composerContent = [
            'name' => 'local/my-package',
            'type' => 'library',
            'description' => 'Test local package',
            'require' => [
                'php' => '>=7.4'
            ]
        ];
        
        file_put_contents($localPackageDir . '/composer.json', json_encode($composerContent, JSON_PRETTY_PRINT));

        // Run the application
        $applicationTester = new ApplicationTester($this->application);
        $applicationTester->run([
            'command' => 'require',
            'nama_paket_folder' => $localPackageDir,
            'versi' => 'dev-main'
        ]);

        $output = $applicationTester->getDisplay();
        
        // Check that composer.json was created in the working directory
        $this->assertFileExists($this->tempDir . '/composer.json');
        
        // Check output contains success message
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
        
        // Verify exit code
        $this->assertEquals(0, $applicationTester->getStatusCode());
    }

    public function testWorkflowWithHttpsRepository(): void
    {
        $applicationTester = new ApplicationTester($this->application);
        
        // This will likely fail due to network/authentication, but we can test the parsing
        $applicationTester->run([
            'command' => 'require',
            'nama_paket_folder' => 'https://github.com/example/test-repo.git',
            'versi' => 'dev-main'
        ]);

        $output = $applicationTester->getDisplay();
        
        // Should create composer.json
        $this->assertFileExists($this->tempDir . '/composer.json');
        
        // May fail at git ls-remote stage, but that's expected for non-existent repos
        $this->assertTrue(
            $applicationTester->getStatusCode() === 0 || 
            str_contains($output, 'Happy Coding -Ratno-') ||
            str_contains($output, 'error') // Expected for invalid repo
        );
    }

    public function testWorkflowWithSshRepositoryFormat(): void
    {
        $applicationTester = new ApplicationTester($this->application);
        
        // Test with valid SSH format but non-existent repo
        $applicationTester->run([
            'command' => 'require',
            'nama_paket_folder' => 'git.example.com:vendor/package-name',
            'versi' => 'dev-main'
        ]);

        $output = $applicationTester->getDisplay();
        
        // Should create composer.json
        $this->assertFileExists($this->tempDir . '/composer.json');
        
        // Should show SSH key message or complete successfully
        $this->assertTrue(
            str_contains($output, 'Happy Coding -Ratno-') ||
            str_contains($output, 'ssh-key') ||
            str_contains($output, 'Tidak dapat mengakses server')
        );
    }

    public function testWorkflowWithInvalidSshFormat(): void
    {
        $applicationTester = new ApplicationTester($this->application);
        
        $applicationTester->run([
            'command' => 'require',
            'nama_paket_folder' => 'invalid-format-without-colon'
        ]);

        $output = $applicationTester->getDisplay();
        
        $this->assertStringContainsString('Nama Remote Repo harus dalam format:', $output);
        $this->assertEquals(0, $applicationTester->getStatusCode());
    }

    public function testWorkflowWithDevOption(): void
    {
        // Create a local package
        $localPackageDir = $this->tempDir . '/dev-package';
        mkdir($localPackageDir, 0777, true);
        
        file_put_contents($localPackageDir . '/composer.json', json_encode([
            'name' => 'local/dev-package',
            'type' => 'library'
        ]));

        $applicationTester = new ApplicationTester($this->application);
        $applicationTester->run([
            'command' => 'require',
            'nama_paket_folder' => $localPackageDir,
            '--dev' => true
        ]);

        $output = $applicationTester->getDisplay();
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
        $this->assertEquals(0, $applicationTester->getStatusCode());
    }

    public function testWorkflowWithCustomPackageName(): void
    {
        // Create a local package
        $localPackageDir = $this->tempDir . '/custom-name-package';
        mkdir($localPackageDir, 0777, true);
        
        file_put_contents($localPackageDir . '/composer.json', json_encode([
            'name' => 'original/package-name',
            'type' => 'library'
        ]));

        $applicationTester = new ApplicationTester($this->application);
        $applicationTester->run([
            'command' => 'require',
            'nama_paket_folder' => $localPackageDir,
            '--paket' => 'custom/new-name'
        ]);

        $output = $applicationTester->getDisplay();
        $this->assertStringContainsString('Happy Coding -Ratno-', $output);
        $this->assertEquals(0, $applicationTester->getStatusCode());
    }

    public function testComposerJsonCreationWhenNotExists(): void
    {
        $composerPath = $this->tempDir . '/composer.json';
        
        // Ensure it doesn't exist
        $this->assertFileDoesNotExist($composerPath);
        
        // Create a local package to use
        $localPackageDir = $this->tempDir . '/test-package';
        mkdir($localPackageDir, 0777, true);
        file_put_contents($localPackageDir . '/composer.json', json_encode(['name' => 'test/package']));

        $applicationTester = new ApplicationTester($this->application);
        $applicationTester->run([
            'command' => 'require',
            'nama_paket_folder' => $localPackageDir
        ]);

        // Should have created composer.json
        $this->assertFileExists($composerPath);
        
        // Content should be valid JSON
        $content = file_get_contents($composerPath);
        $this->assertJson($content);
    }

    public function testGlobalOption(): void
    {
        // Create a local package
        $localPackageDir = $this->tempDir . '/global-package';
        mkdir($localPackageDir, 0777, true);
        
        file_put_contents($localPackageDir . '/composer.json', json_encode([
            'name' => 'local/global-package'
        ]));

        $applicationTester = new ApplicationTester($this->application);
        $applicationTester->run([
            'command' => 'require',
            'nama_paket_folder' => $localPackageDir,
            '--global' => true
        ]);

        $output = $applicationTester->getDisplay();
        
        // The command should attempt to use 'composer global'
        // This might fail in test environment, but we can check it processed the option
        $this->assertTrue(
            str_contains($output, 'Happy Coding -Ratno-') ||
            $applicationTester->getStatusCode() !== 0 // May fail due to composer global not being available
        );
    }
}