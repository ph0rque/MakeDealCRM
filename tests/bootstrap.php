<?php
/**
 * PHPUnit Bootstrap File
 * 
 * Sets up the testing environment for MakeDeal CRM
 */

// Define testing environment
define('TESTING', true);
define('TEST_ROOT', __DIR__);
define('APP_ROOT', dirname(__DIR__));

// Load composer autoloader if available
$composerAutoload = APP_ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Load application bootstrap
$appBootstrap = APP_ROOT . '/includes/bootstrap.php';
if (file_exists($appBootstrap)) {
    require_once $appBootstrap;
}

// Set up test database connection
if (!defined('DB_CONNECTION')) {
    define('DB_CONNECTION', 'sqlite');
    define('DB_DATABASE', ':memory:');
}

// Load test helpers
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/DatabaseTestCase.php';
require_once __DIR__ . '/ApiTestCase.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone
date_default_timezone_set('UTC');

// Create test directories if they don't exist
$testDirs = [
    __DIR__ . '/coverage',
    __DIR__ . '/logs',
    __DIR__ . '/tmp'
];

foreach ($testDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Load test fixtures
require_once __DIR__ . '/fixtures/PipelineFixtures.php';