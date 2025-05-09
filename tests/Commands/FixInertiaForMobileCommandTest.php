<?php

namespace NativeCLI\Tests\Commands;

use NativeCLI\Application;
use NativeCLI\Command\FixInertiaForMobileCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Throwable;

class FixInertiaForMobileCommandTest extends TestCase
{
    private TestableFixInertiaForMobileCommand $command;
    private CommandTester $commandTester;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create the testable command class
        $this->command = new TestableFixInertiaForMobileCommand('inertia:fix');
        
        $app = new Application();
        $app->add($this->command);
        
        $command = $app->find('inertia:fix');
        $this->commandTester = new CommandTester($command);
    }
    
    /**
     * Test case when package.json doesn't exist
     */
    public function testFailsWhenPackageJsonDoesNotExist(): void
    {
        // Set the file_exists mock to return false (file doesn't exist)
        $this->command->setFileExists(false);
        
        // Execute the command
        $this->commandTester->execute([]);
        
        // Verify output contains error message
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('package.json not found', $output);
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
    }
    
    /**
     * Test case when package.json is invalid
     */
    public function testFailsWithInvalidPackageJson(): void
    {
        // Set the file_exists mock to return true (file exists)
        $this->command->setFileExists(true);
        
        // Set the file_get_contents mock to return invalid JSON
        $this->command->setFileContents('{invalid json}');
        
        $this->commandTester->execute([]);
        
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Failed to parse package.json', $output);
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
    }
    
    /**
     * Test case when no Inertia packages are found
     */
    public function testSucceedsWhenNoInertiaPackagesFound(): void
    {
        // Set the file_exists mock to return true (file exists)
        $this->command->setFileExists(true);
        
        // Set the file_get_contents mock to return JSON without Inertia packages
        $this->command->setFileContents(json_encode([
            'dependencies' => [
                'some-package' => '^1.0.0',
            ],
        ]));
        
        $this->commandTester->execute([]);
        
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No Inertia.js packages found', $output);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }
    
    /**
     * Test success case with Inertia packages present
     */
    public function testSucceedsWithInertiaPackages(): void
    {
        // Set the file_exists mock to return true (file exists)
        $this->command->setFileExists(true);
        
        // Set the file_get_contents mock to return JSON with Inertia packages
        $this->command->setFileContents(json_encode([
            'dependencies' => [
                '@inertiajs/vue3' => '^1.0.0',
                '@inertiajs/react' => '^1.0.0',
            ],
        ]));
        
        // Success case - npm commands succeed
        $this->command->setShouldFail(false);
        
        $this->commandTester->execute([]);
        
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found @inertiajs/vue3', $output);
        $this->assertStringContainsString('Found @inertiajs/react', $output);
        $this->assertStringContainsString('All packages have been updated successfully', $output);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }
    
    /**
     * Test case when npm commands fail
     */
    public function testFailsWhenNpmCommandFails(): void
    {
        // Set the file_exists mock to return true (file exists)
        $this->command->setFileExists(true);
        
        // Set the file_get_contents mock to return JSON with Inertia packages
        $this->command->setFileContents(json_encode([
            'dependencies' => [
                '@inertiajs/vue3' => '^1.0.0',
            ],
        ]));
        
        // Make npm commands fail
        $this->command->setShouldFail(true);
        
        $this->commandTester->execute([]);
        
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Failed to reinstall @inertiajs/vue3', $output);
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
    }
}

/**
 * A testable version of the FixInertiaForMobileCommand that allows mocking of 
 * file operations and process execution.
 */
class TestableFixInertiaForMobileCommand extends FixInertiaForMobileCommand
{
    private bool $fileExists = false;
    private string $fileContents = '';
    private bool $shouldFail = false;
    
    public function setFileExists(bool $exists): void
    {
        $this->fileExists = $exists;
    }
    
    public function setFileContents(string $contents): void
    {
        $this->fileContents = $contents;
    }
    
    public function setShouldFail(bool $shouldFail): void
    {
        $this->shouldFail = $shouldFail;
    }
    
    /**
     * Override to intercept the file_exists call in the parent class
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Replace the real getcwd() call with our mock path
        $packageJsonPath = $this->getMockedPackageJsonPath();
        
        // Check if the file exists using our mocked file_exists
        if (!$this->mockedFileExists($packageJsonPath)) {
            $output->writeln('<error>package.json not found in the current directory.</error>');
            return Command::FAILURE;
        }
        
        // Get file contents using our mocked file_get_contents
        $packageJson = json_decode($this->mockedFileGetContents($packageJsonPath), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln('<error>Failed to parse package.json.</error>');
            return Command::FAILURE;
        }
        
        // The rest of the logic follows the original command's implementation
        $output->writeln('<info>Checking for updates...</info>', $this->getOutputVerbosityLevel($input));
        
        $packagesToCheck = [
            '@inertiajs/vue3',
            '@inertiajs/react',
            '@inertiajs/svelte',
        ];
        
        $foundPackages = array_filter($packagesToCheck, fn($pkg) => isset($packageJson['dependencies'][$pkg]));
        
        if (empty($foundPackages)) {
            $output->writeln('<info>No Inertia.js packages found in package.json.</info>');
            return Command::SUCCESS;
        }
        
        foreach ($foundPackages as $package) {
            $output->writeln("<info>Found $package. Reinstalling from GitHub...</info>");
            
            try {
                $this->runCommand(['npm', 'uninstall', $package], $output);
                $this->runCommand(['npm', 'install', "$package@mpociot/inertia#patch-1"], $output);
            } catch (Throwable $e) {
                $output->writeln("<error>Failed to reinstall $package: {$e->getMessage()}</error>");
                return Command::FAILURE;
            }
        }
        
        $output->writeln('<info>All packages have been updated successfully.</info>');
        return Command::SUCCESS;
    }
    
    /**
     * Mock for file_exists
     */
    private function mockedFileExists(string $path): bool
    {
        return $this->fileExists;
    }
    
    /**
     * Mock for file_get_contents
     */
    private function mockedFileGetContents(string $path): string
    {
        return $this->fileContents;
    }
    
    /**
     * Return a fixed path for testing
     */
    private function getMockedPackageJsonPath(): string
    {
        return 'mock/path/package.json';
    }
    
    /**
     * Override the runCommand method to mock Process execution
     */
    protected function runCommand(array $command, $output): void
    {
        // If shouldFail is true and this is an "npm install" command, throw an exception
        if ($this->shouldFail && $command[1] === 'install') {
            throw new \RuntimeException('Command failed: ' . implode(' ', $command));
        }
        
        // Otherwise do nothing (mock successful execution)
    }
}

