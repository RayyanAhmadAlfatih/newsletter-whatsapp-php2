<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token($token, 'logout')) {
    log_security_event('Upaya logout dengan CSRF token tidak valid dari IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

$adminUser = $_SESSION['admin_username'] ?? 'unknown';
log_security_event('Admin logout untuk pengguna ' . $adminUser . ' dari IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

logout_admin();

session_start();
$currentTime = time();
$_SESSION['_security'] = [
    'initialized_at' => $currentTime,
    'last_activity' => $currentTime,
    'user_agent' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'cli'),
    'ip_address' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
];
$_SESSION['_csrf_tokens'] = [];
$_SESSION['success'] = 'Anda telah keluar dengan aman.';

header('Location: index.php');
exit;
