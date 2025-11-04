<?php
// TEMP: enable verbose error reporting during setup (remove in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!isset($_ENV) || !is_array($_ENV)) {
    $_ENV = [];
}

$projectRoot = dirname(__DIR__);

// Load environment variables from .env if available
$envFilePath = $projectRoot . '/.env';
if (is_readable($envFilePath)) {
    $envLines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($envLines !== false) {
        foreach ($envLines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            if ($value !== '' && (
                ($value[0] === '"' && substr($value, -1) === '"') ||
                ($value[0] === "'" && substr($value, -1) === "'")
            )) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($name, $_ENV) && getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }
    }
}

// Load configuration array if available
$config = [];
$configPath = $projectRoot . '/config.php';
if (is_readable($configPath)) {
    $config = require $configPath;
    if (!is_array($config)) {
        throw new RuntimeException('The config.php file must return an associative array of configuration values.');
    }
}

$loadValue = static function (string $key, $default = null) use ($config) {
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    $envValue = getenv($key);
    if ($envValue !== false) {
        return $envValue;
    }

    if (array_key_exists($key, $config)) {
        return $config[$key];
    }

    return $default;
};


// Database configuration
define('DB_HOST', (string) $loadValue('DB_HOST', 'localhost'));
define('DB_NAME', (string) $loadValue('DB_NAME', 'newsletter_wa'));
define('DB_USER', (string) $loadValue('DB_USER', 'root'));
define('DB_PASS', (string) $loadValue('DB_PASS', ''));

// Fonnte configuration
define('FONNTE_API_KEY', (string) $loadValue('FONNTE_API_KEY', ''));
define('FONNTE_API_URL', (string) $loadValue('FONNTE_API_URL', 'https://api.fonnte.com/send'));

// Base URL for CLI/cron usage
define('BASE_URL', (string) $loadValue('BASE_URL', 'http://localhost'));

// Admin credentials
$adminUsername = $loadValue('ADMIN_USERNAME');
if ($adminUsername !== null && $adminUsername !== '') {
    define('ADMIN_USERNAME', (string) $adminUsername);
}

$adminPasswordHash = $loadValue('ADMIN_PASSWORD_HASH');
if ($adminPasswordHash !== null && $adminPasswordHash !== '') {
    define('ADMIN_PASSWORD_HASH', (string) $adminPasswordHash);
}

$adminPasswordPlain = $loadValue('ADMIN_PASSWORD');
if ($adminPasswordPlain !== null && $adminPasswordPlain !== '') {
    define('ADMIN_PASSWORD', (string) $adminPasswordPlain);
}

// Database connection using PDO
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
