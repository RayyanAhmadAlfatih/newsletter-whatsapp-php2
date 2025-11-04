<?php
declare(strict_types=1);

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

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            if ($value !== '' && (
                ($value[0] === '"' && substr($value, -1) === '"') ||
                ($value[0] === '\'' && substr($value, -1) === '\'')
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

$debugMode = filter_var($loadValue('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
$environment = (string) $loadValue('APP_ENV', 'production');

// Error handling configuration
ini_set('display_errors', $debugMode ? '1' : '0');
ini_set('display_startup_errors', $debugMode ? '1' : '0');
ini_set('log_errors', '1');
$errorLogPath = (string) $loadValue('ERROR_LOG_PATH', $projectRoot . '/storage/logs/app.log');
if (!is_dir(dirname($errorLogPath))) {
    @mkdir(dirname($errorLogPath), 0750, true);
}
ini_set('error_log', $errorLogPath);

// Session security directives
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');

if (!function_exists('app_reset_session')) {
    function app_reset_session(bool $regenerateId = true): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if ($regenerateId) {
            session_regenerate_id(true);
        }
    }
}

$sessionName = (string) $loadValue('SESSION_NAME', 'newsletter_session');
$sessionTimeout = (int) $loadValue('SESSION_TIMEOUT', 1800);
$sessionSameSite = strtoupper((string) $loadValue('SESSION_SAMESITE', 'Strict'));

if ($sessionSameSite === '') {
    $sessionSameSite = 'Strict';
}

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $sessionSameSite,
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    session_name($sessionName);
    session_start();
}

$currentTime = time();
$userAgentHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'cli');
$ipAddressHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

if (!isset($_SESSION['_security']) || !is_array($_SESSION['_security'])) {
    session_regenerate_id(true);
    $_SESSION['_security'] = [
        'initialized_at' => $currentTime,
        'last_activity' => $currentTime,
        'user_agent' => $userAgentHash,
        'ip_address' => $ipAddressHash,
    ];
} else {
    $security = $_SESSION['_security'];

    if ($sessionTimeout > 0 && isset($security['last_activity']) && ($currentTime - (int) $security['last_activity']) > $sessionTimeout) {
        $wasAuthenticated = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
        app_reset_session();
        $_SESSION['_security'] = [
            'initialized_at' => $currentTime,
            'last_activity' => $currentTime,
            'user_agent' => $userAgentHash,
            'ip_address' => $ipAddressHash,
        ];
        if ($wasAuthenticated) {
            $_SESSION['error'] = 'Sesi Anda telah kedaluwarsa. Silakan login kembali.';
        }
    } else {
        $expectedUserAgent = $security['user_agent'] ?? null;
        $expectedIpAddress = $security['ip_address'] ?? null;

        if (($expectedUserAgent && !hash_equals($expectedUserAgent, $userAgentHash)) ||
            ($expectedIpAddress && !hash_equals($expectedIpAddress, $ipAddressHash))) {
            $wasAuthenticated = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
            error_log('Session integrity check failed: regenerating session.');
            app_reset_session();
            $_SESSION['_security'] = [
                'initialized_at' => $currentTime,
                'last_activity' => $currentTime,
                'user_agent' => $userAgentHash,
                'ip_address' => $ipAddressHash,
            ];
            if ($wasAuthenticated) {
                $_SESSION['error'] = 'Sesi Anda tidak valid. Silakan login kembali.';
            }
        } else {
            $_SESSION['_security']['last_activity'] = $currentTime;
            $_SESSION['_security']['user_agent'] = $userAgentHash;
            $_SESSION['_security']['ip_address'] = $ipAddressHash;
        }
    }
}

if (!isset($_SESSION['_csrf_tokens']) || !is_array($_SESSION['_csrf_tokens'])) {
    $_SESSION['_csrf_tokens'] = [];
}

// Security headers
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    if (!defined('SECURITY_HEADERS_SENT')) {
        define('SECURITY_HEADERS_SENT', true);
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('X-XSS-Protection: 0');
        header(
            "Content-Security-Policy: default-src 'self'; " .
            "script-src 'self' 'unsafe-inline'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data:; " .
            "connect-src 'self'; " .
            "font-src 'self'; " .
            "frame-ancestors 'self'; " .
            "form-action 'self'; " .
            "base-uri 'self';"
        );
    }
}

// Database configuration
define('DB_HOST', (string) $loadValue('DB_HOST', 'localhost'));
define('DB_NAME', (string) $loadValue('DB_NAME', 'newsletter_wa'));
define('DB_USER', (string) $loadValue('DB_USER', 'root'));
define('DB_PASS', (string) $loadValue('DB_PASS', ''));

define('FONNTE_API_KEY', (string) $loadValue('FONNTE_API_KEY', ''));
define('FONNTE_API_URL', (string) $loadValue('FONNTE_API_URL', 'https://api.fonnte.com/send'));
define('BASE_URL', (string) $loadValue('BASE_URL', 'http://localhost'));

define('SESSION_TIMEOUT_SECONDS', $sessionTimeout);

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

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Terjadi kesalahan pada koneksi database.');
}
