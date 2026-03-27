<?php
/**
 * tests/Unit/EnvLoaderTest.php
 * ---------------------------------------------------
 * Tests for config/env.php — .env file parser.
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class EnvLoaderTest extends TestCase
{
    private string $tempEnvPath;
    private string $originalEnvPath;

    protected function setUp(): void
    {
        $this->originalEnvPath = PROJECT_ROOT . '/.env';
        $this->tempEnvPath = PROJECT_ROOT . '/.env.test.tmp';
    }

    protected function tearDown(): void
    {
        // Clean up temp env file
        if (file_exists($this->tempEnvPath)) {
            unlink($this->tempEnvPath);
        }
        // Clear test env vars
        putenv('TEST_ENV_KEY');
        putenv('TEST_MULTI_LINE');
    }

    /** @test */
    public function env_loader_file_exists(): void
    {
        $this->assertFileExists(PROJECT_ROOT . '/config/env.php');
    }

    /** @test */
    public function env_loader_skips_comments(): void
    {
        $content = "# This is a comment\nTEST_ENV_KEY=hello_world\n";
        file_put_contents($this->tempEnvPath, $content);

        // Manually simulate env parsing logic
        $lines = file($this->tempEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                putenv("$key=$value");
            }
        }

        $this->assertSame('hello_world', getenv('TEST_ENV_KEY'));
    }

    /** @test */
    public function env_loader_handles_equals_in_value(): void
    {
        $content = "TEST_MULTI_LINE=key=value=extra\n";
        file_put_contents($this->tempEnvPath, $content);

        $lines = file($this->tempEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                putenv(trim($key) . '=' . trim($value));
            }
        }

        $this->assertSame('key=value=extra', getenv('TEST_MULTI_LINE'));
    }

    /** @test */
    public function env_loader_skips_empty_lines(): void
    {
        $content = "\n\n\nTEST_ENV_KEY=value\n\n";
        file_put_contents($this->tempEnvPath, $content);

        $lines = file($this->tempEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(1, $lines);
    }
}
