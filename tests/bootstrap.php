<?php
/**
 * tests/bootstrap.php
 * ---------------------------------------------------
 * PHPUnit test bootstrap.
 * Sets up the environment for all tests without needing
 * a live database or web server.
 * ---------------------------------------------------
 */

// Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Simulate a CLI test environment so session_start() etc. don't fail
if (session_status() === PHP_SESSION_NONE) {
    // Suppress "cannot send session cookie" warnings in CLI
    @session_start();
}

// Set test env vars used by config/env.php
putenv('APP_URL=http://localhost/TiranaSolidare');

// Define project root for convenience in tests
define('PROJECT_ROOT', dirname(__DIR__));
