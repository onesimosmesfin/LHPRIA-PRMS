<?php
// config/db.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'prms_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

define('DB_HOST', $db_host);
define('DB_PORT', $db_port);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);

// Resilient connection with automatic fallbacks for various MySQL/MariaDB environments
$connection_attempts = [
    ['host' => $db_host,     'port' => $db_port, 'user' => $db_user,     'pass' => $db_pass],
    ['host' => '127.0.0.1',  'port' => '3306',   'user' => 'prms_user',  'pass' => 'prms_password_2026'],
    ['host' => 'localhost',  'port' => '3306',   'user' => 'prms_user',  'pass' => 'prms_password_2026'],
    ['host' => '127.0.0.1',  'port' => '3306',   'user' => 'root',       'pass' => ''],
    ['host' => 'localhost',  'port' => '3306',   'user' => 'root',       'pass' => '']
];

$pdo = null;
$last_error = '';

foreach ($connection_attempts as $att) {
    try {
        $dsn = "mysql:host=" . $att['host'] . ";port=" . $att['port'] . ";dbname=" . $db_name . ";charset=utf8mb4";
        $pdo = new PDO($dsn, $att['user'], $att['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        break;
    } catch (PDOException $e) {
        $last_error = $e->getMessage();
    }
}

if (!$pdo) {
    die("Database Connection Error: " . $last_error);
}

function base_url($path = '')
{
    // Auto-detect base path: if requested under /land/, prefix with /land/, otherwise /
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($uri, '/land/') !== false || strpos($script, '/land/') !== false) {
        return '/land/' . ltrim($path, '/');
    }
    return '/' . ltrim($path, '/');
}
