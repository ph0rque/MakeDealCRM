<?php
/***CONFIGURATOR***/
// SuiteCRM CI Environment Configuration Override

$sugar_config['dbconfig']['db_host_name'] = getenv('DB_HOST') ?: 'localhost';
$sugar_config['dbconfig']['db_host_instance'] = 'SQLEXPRESS';
$sugar_config['dbconfig']['db_user_name'] = getenv('DB_USER') ?: 'root';
$sugar_config['dbconfig']['db_password'] = getenv('DB_PASSWORD') ?: '';
$sugar_config['dbconfig']['db_name'] = getenv('DB_NAME') ?: 'suitecrm_test';
$sugar_config['dbconfig']['db_type'] = 'mysql';
$sugar_config['dbconfig']['db_port'] = getenv('DB_PORT') ?: '3306';
$sugar_config['dbconfig']['db_manager'] = 'MysqliManager';

// Site configuration
$sugar_config['site_url'] = getenv('BASE_URL') ?: 'http://localhost:8080';
$sugar_config['host_name'] = parse_url($sugar_config['site_url'], PHP_URL_HOST);
$sugar_config['site_url'] = rtrim($sugar_config['site_url'], '/');

// Cache configuration
$sugar_config['cache_dir'] = 'cache/';
$sugar_config['tmp_dir'] = 'cache/xml/';
$sugar_config['upload_dir'] = 'upload/';

// Performance settings for CI
$sugar_config['developerMode'] = false;
$sugar_config['disable_vcr'] = true;
$sugar_config['disable_count_query'] = true;
$sugar_config['save_query'] = 'N';
$sugar_config['slow_query_time_msec'] = '1000';

// Security settings
$sugar_config['passwordsetting']['generatepasswordtmpl'] = true;
$sugar_config['passwordsetting']['forgotpasswordON'] = false;
$sugar_config['passwordsetting']['linkexpiration'] = 1;
$sugar_config['passwordsetting']['linkexpirationtime'] = 30;
$sugar_config['passwordsetting']['linkexpirationtype'] = 60;
$sugar_config['passwordsetting']['systexpiration'] = 0;
$sugar_config['passwordsetting']['systexpirationtime'] = 0;
$sugar_config['passwordsetting']['systexpirationtype'] = 0;
$sugar_config['passwordsetting']['systexpirationlogin'] = 0;

// Email settings (disabled for CI)
$sugar_config['allow_email_outbound'] = false;

// Session configuration
$sugar_config['session_dir'] = '';
$sugar_config['unique_key'] = 'ci_test_unique_key_' . getenv('BUILD_NUMBER');

// Logging
$sugar_config['logger']['level'] = 'error';
$sugar_config['logger']['file']['maxSize'] = '10MB';
$sugar_config['logger']['file']['maxLogs'] = 10;
$sugar_config['logger']['file']['name'] = 'suitecrm';
$sugar_config['logger']['file']['dateFormat'] = '%c';
$sugar_config['logger']['file']['suffix'] = '';

// Disable installer
$sugar_config['installer_locked'] = true;

// Time zone
$sugar_config['default_timezone'] = 'UTC';

// Language
$sugar_config['default_language'] = 'en_us';

// Theme
$sugar_config['default_theme'] = 'SuiteP';

// API settings
$sugar_config['oauth2']['access_token_lifetime'] = 3600;
$sugar_config['oauth2']['refresh_token_lifetime'] = 1209600;

// Redis configuration if available
if (getenv('REDIS_HOST')) {
    $sugar_config['external_cache_disabled'] = false;
    $sugar_config['external_cache_disabled_redis'] = false;
    $sugar_config['cache_class'] = 'SugarCacheRedis';
    $sugar_config['redis_config']['host'] = getenv('REDIS_HOST');
    $sugar_config['redis_config']['port'] = getenv('REDIS_PORT') ?: 6379;
    $sugar_config['redis_config']['timeout'] = 2.5;
    $sugar_config['redis_config']['database'] = 0;
} else {
    $sugar_config['external_cache_disabled'] = true;
}

// Test-specific configurations
if (getenv('ENVIRONMENT') === 'test' || getenv('CI') === 'true') {
    $sugar_config['sql_debugging'] = false;
    $sugar_config['dump_slow_queries'] = false;
    $sugar_config['track_slow_queries'] = false;
    
    // Disable some modules for faster testing
    $sugar_config['hide_subpanels'] = true;
    
    // Test user configuration
    $sugar_config['test_admin_user'] = getenv('ADMIN_USERNAME') ?: 'admin';
    $sugar_config['test_admin_pass'] = getenv('ADMIN_PASSWORD') ?: 'admin123';
    
    // Faster session timeout for tests
    $sugar_config['session_timeout'] = 7200; // 2 hours
}

// Resource limits
$sugar_config['resource_management']['special_query_limit'] = 50000;
$sugar_config['resource_management']['special_query_modules'] = array(
    'Reports',
    'Campaigns',
    'CampaignLog'
);

// Image settings
$sugar_config['image_max_width'] = 1024;
$sugar_config['image_max_height'] = 1024;

// Import settings
$sugar_config['import_max_records_per_file'] = 1000;

/***CONFIGURATOR***/