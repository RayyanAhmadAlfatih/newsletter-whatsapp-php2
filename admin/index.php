<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'helpers.php';

if (!function_exists('isAdminPasswordValid')) {
    function isAdminPasswordValid(string $inputPassword): bool
    {
        if (defined('ADMIN_PASSWORD_HASH') && ADMIN_PASSWORD_HASH !== '') {
            return password_verify($inputPassword, ADMIN_PASSWORD_HASH);
        }

        if (defined('ADMIN_PASSWORD') && ADMIN_PASSWORD !== '') {
            log_security_event('Plain-text ADMIN_PASSWORD is in use. Please migrate to ADMIN_PASSWORD_HASH using password_hash().');
            return hash_equals((string) ADMIN_PASSWORD, (string) $inputPassword);
        }

        return false;
    }
}

if (is_admin_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$loginConfigReady = defined('ADMIN_USERNAME') && (
    (defined('ADMIN_PASSWORD_HASH') && ADMIN_PASSWORD_HASH !== '') ||
    (defined('ADMIN_PASSWORD') && ADMIN_PASSWORD !== '')
);

$configWarning = $loginConfigReady
    ? ''
    : 'Konfigurasi login admin belum diatur. Set ADMIN_USERNAME dan ADMIN_PASSWORD_HASH (atau ADMIN_PASSWORD) melalui .env atau config.php.';

$maxAttempts = 5;
$lockoutSeconds = 900;
$loginSecurity = $_SESSION['login_security'] ?? ['attempts' => 0];
$lockoutRemaining = 0;
$flashSuccess = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

if (isset($loginSecurity['locked_until']) && $loginSecurity['locked_until'] > time()) {
    $lockoutRemaining = $loginSecurity['locked_until'] - time();
} elseif (isset($loginSecurity['locked_until']) && $loginSecurity['locked_until'] <= time()) {
    $loginSecurity = ['attempts' => 0];
    $_SESSION['login_security'] = $loginSecurity;
}

$error = '';
$lockoutMessage = '';

if ($lockoutRemaining > 0) {
    $minutes = (int) ceil($lockoutRemaining / 60);
    $lockoutMessage = "Login terkunci untuk sementara. Coba lagi dalam {$minutes} menit.";
}

if (isset($_SESSION['error'])) {
    $error = (string) $_SESSION['error'];
    unset($_SESSION['error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$loginConfigReady) {
        $error = $configWarning ?: 'Konfigurasi login admin belum diatur.';
    } elseif ($lockoutRemaining > 0) {
        $error = $lockoutMessage;
    } else {
        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!verify_csrf_token($csrfToken, 'admin_login')) {
            $error = 'Sesi login tidak valid atau telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
        } else {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            $isUsernameValid = defined('ADMIN_USERNAME') && hash_equals((string) ADMIN_USERNAME, $username);

            if ($isUsernameValid && isAdminPasswordValid($password)) {
                app_reset_session();
                $currentTime = time();
                $_SESSION['_security'] = [
                    'initialized_at' => $currentTime,
                    'last_activity' => $currentTime,
                    'user_agent' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'cli'),
                    'ip_address' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                ];
                $_SESSION['_csrf_tokens'] = [];
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = ADMIN_USERNAME;
                $_SESSION['login_security'] = ['attempts' => 0, 'locked_until' => 0];

                log_security_event('Admin login sukses untuk pengguna ' . ADMIN_USERNAME . ' dari IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

                header('Location: dashboard.php');
                exit;
            }

            log_security_event('Gagal login admin untuk username ' . $username . ' dari IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            $loginSecurity['attempts'] = (int) ($loginSecurity['attempts'] ?? 0) + 1;
            $loginSecurity['last_attempt_at'] = time();

            if ($loginSecurity['attempts'] >= $maxAttempts) {
                $loginSecurity['locked_until'] = time() + $lockoutSeconds;
                $lockoutRemaining = $lockoutSeconds;
                $minutes = (int) ceil($lockoutRemaining / 60);
                $lockoutMessage = "Login terkunci untuk sementara. Coba lagi dalam {$minutes} menit.";
            }

            $_SESSION['login_security'] = $loginSecurity;
            $error = 'Username atau password salah!';
        }
    }
}

$canSubmit = $loginConfigReady && $lockoutRemaining === 0;
$disabledAttribute = $canSubmit ? '' : 'disabled';
$csrfFieldValue = csrf_token('admin_login');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Newsletter WhatsApp Rayyan</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>🔐 Admin Login</h2>
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?php echo e($flashSuccess); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <?php if ($configWarning && $error !== $configWarning): ?>
                <div class="alert alert-warning"><?php echo e($configWarning); ?></div>
            <?php endif; ?>

            <?php if ($lockoutMessage && $error !== $lockoutMessage): ?>
                <div class="alert alert-warning"><?php echo e($lockoutMessage); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="index.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfFieldValue); ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus <?php echo $disabledAttribute ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required <?php echo $disabledAttribute ? 'disabled' : ''; ?>>
                </div>
                
                <button type="submit" class="btn-primary" <?php echo $disabledAttribute ? 'disabled' : ''; ?>>Login</button>
            </form>
            
            <div class="login-footer">
                <a href="../index.php">← Kembali ke Halaman Utama</a>
            </div>
        </div>
    </div>
</body>
</html>
