<?php
// TEMP: enable error reporting for debugging login blank page
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

require_once 'db.php';

if (!function_exists('isAdminPasswordValid')) {
    function isAdminPasswordValid(string $inputPassword): bool
    {
        if (defined('ADMIN_PASSWORD_HASH') && ADMIN_PASSWORD_HASH !== '') {
            return password_verify($inputPassword, ADMIN_PASSWORD_HASH);
        }

        if (defined('ADMIN_PASSWORD') && ADMIN_PASSWORD !== '') {
            return hash_equals((string) ADMIN_PASSWORD, (string) $inputPassword);
        }

        return false;
    }
}

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$loginConfigReady = defined('ADMIN_USERNAME') && (
    (defined('ADMIN_PASSWORD_HASH') && ADMIN_PASSWORD_HASH !== '') ||
    (defined('ADMIN_PASSWORD') && ADMIN_PASSWORD !== '')
);

$configWarning = $loginConfigReady
    ? ''
    : 'Konfigurasi login admin belum diatur. Set variabel ADMIN_USERNAME dan ADMIN_PASSWORD_HASH (atau ADMIN_PASSWORD) melalui file .env atau config.php.';

$error = '';
$disabledAttribute = $loginConfigReady ? '' : 'disabled';

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$loginConfigReady) {
        $error = $configWarning ?: 'Konfigurasi login admin belum diatur.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $isUsernameValid = hash_equals((string) ADMIN_USERNAME, (string) $username);
        if ($isUsernameValid && isAdminPasswordValid($password)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Username atau password salah!';
    }
}
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
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($configWarning && $error !== $configWarning): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($configWarning, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="index.php">
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
