<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'helpers.php';

require_admin_auth();

$success = '';
$error = '';
$formSubmitted = $_SERVER['REQUEST_METHOD'] === 'POST';
$resetForm = false;
$logoutToken = csrf_token('logout');
$formToken = csrf_token('add_message');

if ($formSubmitted) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null, 'add_message')) {
        $error = 'Sesi formulir tidak valid. Silakan refresh halaman dan coba lagi.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $delay_days = (int) max(0, (int) ($_POST['delay_days'] ?? 0));
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $file_url = null;

        if ($title === '' || $content === '') {
            $error = 'Title dan content tidak boleh kosong!';
        } elseif (mb_strlen($title) > 255) {
            $error = 'Title terlalu panjang. Maksimal 255 karakter.';
        } elseif ($delay_days > 365) {
            $error = 'Jeda hari maksimal 365.';
        } else {
            if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = upload_file($_FILES['media_file'], 'messages');

                if ($upload_result['success']) {
                    $file_url = $upload_result['path'];
                } else {
                    $error = 'Error upload file: ' . $upload_result['message'];
                }
            }

            if ($error === '') {
                try {
                    $stmt = $pdo->prepare('INSERT INTO messages (title, content, delay_days, file_url, is_active) VALUES (:title, :content, :delay_days, :file_url, :is_active)');
                    $stmt->bindValue(':title', $title, PDO::PARAM_STR);
                    $stmt->bindValue(':content', $content, PDO::PARAM_STR);
                    $stmt->bindValue(':delay_days', $delay_days, PDO::PARAM_INT);
                    $stmt->bindValue(':file_url', $file_url, $file_url === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $stmt->bindValue(':is_active', $is_active, PDO::PARAM_INT);
                    $stmt->execute();

                    $success = 'Pesan berhasil ditambahkan!';
                    $resetForm = true;
                    $_POST = [];
                    $formToken = csrf_token('add_message');
                } catch (PDOException $exception) {
                    if ($file_url) {
                        delete_file($file_url);
                    }
                    log_security_event('Gagal menambahkan pesan: ' . $exception->getMessage());
                    $error = 'Terjadi kesalahan saat menyimpan data.';
                }
            }
        }
    }
}
$shouldCheckActive = (!$formSubmitted) || $resetForm || isset($_POST['is_active']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pesan - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-header">
        <h1>✉️ Tambah Pesan Otomatis</h1>
        <div class="admin-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="subscribers.php">Subscribers</a>
            <a href="add_message.php" class="nav-active">Tambah Pesan</a>
            <a href="messages.php">Daftar Pesan</a>
            <a href="logs.php">Log Pengiriman</a>
            <form method="POST" action="logout.php" class="logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo e($logoutToken); ?>">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="admin-container">
        <div class="content-section">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo e($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo e($formToken); ?>">

                <div class="form-group">
                    <label for="title">Judul Pesan <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required maxlength="255"
                           value="<?php echo e($_POST['title'] ?? ''); ?>"
                           placeholder="Contoh: Selamat Datang">
                </div>
                
                <div class="form-group">
                    <label for="content">Isi Pesan <span class="required">*</span></label>
                    <textarea id="content" name="content" rows="6" required
                              placeholder="Masukkan isi pesan yang akan dikirim ke WhatsApp. Gunakan {nama} atau {name} untuk personalisasi."><?php echo e($_POST['content'] ?? ''); ?></textarea>
                    <small>💡 Tips: Gunakan <strong>{nama}</strong> atau <strong>{name}</strong> untuk menyapa subscriber secara personal</small>
                </div>
                
                <div class="form-group">
                    <label for="media_file">File Media (Opsional)</label>
                    <input type="file" id="media_file" name="media_file" accept="image/*,video/*,.pdf">
                    <small>📎 Upload gambar, video, atau PDF (maksimal 10MB). File akan dikirim bersama pesan teks.</small>
                </div>
                
                <div class="form-group">
                    <label for="delay_days">Jeda Hari (setelah pendaftaran) <span class="required">*</span></label>
                    <input type="number" id="delay_days" name="delay_days" required min="0" max="365"
                           value="<?php echo e($_POST['delay_days'] ?? '0'); ?>">
                    <small>0 = hari pertama, 1 = 1 hari setelah daftar, dst.</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?php echo $shouldCheckActive ? 'checked' : ''; ?>>
                        Aktif (pesan akan dikirim)
                    </label>
                </div>
                
                <button type="submit" class="btn-primary">💾 Simpan Pesan</button>
                <a href="messages.php" class="btn-secondary">❌ Batal</a>
            </form>
        </div>
    </div>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
