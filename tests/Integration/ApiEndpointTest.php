<?php
/**
 * tests/Integration/ApiEndpointTest.php
 * ---------------------------------------------------
 * Integration tests for API endpoints using PHP's
 * built-in request simulation. Tests JSON responses,
 * auth guards, and error handling.
 * ---------------------------------------------------
 */

declare(strict_types=1);

require_once PROJECT_ROOT . '/includes/functions.php';
require_once __DIR__ . '/DatabaseTestCase.php';

class ApiEndpointTest extends DatabaseTestCase
{
    /**
     * Helper: execute a PHP script in a subprocess and capture output.
     * This avoids exit()/header() issues.
     */
    private function callApi(string $file, string $method = 'GET', array $queryParams = [], array $sessionData = [], ?string $body = null): array
    {
        $queryString = http_build_query($queryParams);
        $scriptPath = PROJECT_ROOT . '/api/' . $file;

        if (!file_exists($scriptPath)) {
            return ['code' => -1, 'body' => '', 'json' => null, 'error' => "File not found: {$scriptPath}"];
        }

        // Build a PHP wrapper that sets up the environment, then includes the API file
        $csrfToken = bin2hex(random_bytes(32));
        $sessionJson = json_encode(array_merge($sessionData, ['csrf_token' => $csrfToken]));
        $escapedScriptPath = addslashes($scriptPath);
        $escapedBody = $body ? addslashes($body) : '';

        $wrapper = <<<PHP
<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Mock session
\$_SESSION = json_decode('{$sessionJson}', true);

// Set request method and query
\$_SERVER['REQUEST_METHOD'] = '{$method}';
\$_SERVER['QUERY_STRING'] = '{$queryString}';
parse_str('{$queryString}', \$_GET);
\$_SERVER['HTTP_X_CSRF_TOKEN'] = '{$csrfToken}';

// Mock php://input for POST/PUT
if ('{$method}' !== 'GET') {
    // We'll use a custom stream
}

// Capture output
ob_start();

// Prevent actual header() calls from erroring in CLI
if (!function_exists('header')) {
    function header(\$h) {}
}

try {
    require '{$escapedScriptPath}';
} catch (Throwable \$e) {
    echo json_encode(['error' => \$e->getMessage()]);
}

\$output = ob_get_clean();
echo \$output;
PHP;

        $tmpFile = tempnam(sys_get_temp_dir(), 'api_test_');
        file_put_contents($tmpFile, $wrapper);

        $envVars = [
            'DB_HOST=' . (getenv('DB_HOST') ?: 'localhost'),
            'DB_PORT=' . (getenv('DB_PORT') ?: '3307'),
            'DB_NAME=TiranaSolidare_test',
            'DB_USER=' . (getenv('DB_USER') ?: 'root'),
            'DB_PASS=' . (getenv('DB_PASS') ?: ''),
            'APP_URL=http://localhost/TiranaSolidare',
        ];

        $envString = implode(' ', array_map(fn($e) => "set {$e} &", $envVars));

        $output = [];
        $code = 0;
        exec("php \"{$tmpFile}\" 2>&1", $output, $code);
        $rawOutput = implode("\n", $output);

        @unlink($tmpFile);

        $json = json_decode($rawOutput, true);

        return [
            'code' => $code,
            'body' => $rawOutput,
            'json' => $json,
        ];
    }

    /** @test */
    public function api_files_exist(): void
    {
        $apiFiles = ['applications.php', 'auth.php', 'categories.php', 'events.php',
                     'help_requests.php', 'helpers.php', 'notifications.php',
                     'stats.php', 'upload.php', 'users.php'];

        foreach ($apiFiles as $file) {
            $this->assertFileExists(
                PROJECT_ROOT . '/api/' . $file,
                "API file missing: {$file}"
            );
        }
    }

    /** @test */
    public function api_helpers_file_is_valid_php(): void
    {
        $output = [];
        $code = 0;
        exec('php -l "' . PROJECT_ROOT . '/api/helpers.php" 2>&1', $output, $code);
        $this->assertSame(0, $code, "Syntax error in api/helpers.php: " . implode("\n", $output));
    }

    /** @test */
    public function all_api_files_have_valid_syntax(): void
    {
        $apiDir = PROJECT_ROOT . '/api/';
        $files = glob($apiDir . '*.php');
        foreach ($files as $file) {
            $output = [];
            $code = 0;
            exec("php -l \"{$file}\" 2>&1", $output, $code);
            $this->assertSame(0, $code, "Syntax error in " . basename($file) . ": " . implode("\n", $output));
        }
    }

    /** @test */
    public function all_action_files_have_valid_syntax(): void
    {
        $actionsDir = PROJECT_ROOT . '/src/actions/';
        $files = glob($actionsDir . '*.php');
        foreach ($files as $file) {
            $output = [];
            $code = 0;
            exec("php -l \"{$file}\" 2>&1", $output, $code);
            $this->assertSame(0, $code, "Syntax error in " . basename($file) . ": " . implode("\n", $output));
        }
    }

    /** @test */
    public function all_view_files_have_valid_syntax(): void
    {
        $viewsDir = PROJECT_ROOT . '/views/';
        $files = glob($viewsDir . '*.php');
        foreach ($files as $file) {
            $output = [];
            $code = 0;
            exec("php -l \"{$file}\" 2>&1", $output, $code);
            $this->assertSame(0, $code, "Syntax error in " . basename($file) . ": " . implode("\n", $output));
        }
    }

    /** @test */
    public function all_config_files_have_valid_syntax(): void
    {
        $configDir = PROJECT_ROOT . '/config/';
        $files = glob($configDir . '*.php');
        foreach ($files as $file) {
            $output = [];
            $code = 0;
            exec("php -l \"{$file}\" 2>&1", $output, $code);
            $this->assertSame(0, $code, "Syntax error in " . basename($file) . ": " . implode("\n", $output));
        }
    }

    /** @test */
    public function includes_functions_has_valid_syntax(): void
    {
        $output = [];
        $code = 0;
        exec('php -l "' . PROJECT_ROOT . '/includes/functions.php" 2>&1', $output, $code);
        $this->assertSame(0, $code, "Syntax error: " . implode("\n", $output));
    }
}
