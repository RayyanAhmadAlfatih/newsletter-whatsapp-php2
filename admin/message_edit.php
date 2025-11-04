<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'helpers.php';

require_admin_auth();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: messages.php');
    exit;
}

try {
    $fetchStmt = $pdo->prepare('SELECT id, title, content, delay_days, file_url, is_active, created_at, updated_at FROM messages WHERE id = :id');
    $fetchStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $fetchStmt->execute();
    $message = $fetchStmt->fetch();
} catch (PDOException $exception) {
    log_security_event('Gagal mengambil data pesan untuk edit: ' . $exception->getMessage());
    $message = false;
}

if (!$message) {
    header('Location: messages.php');
    exit;
}

$success = '';
$error = '';
$logoutToken = csrf_token('logout');
$formTokenKey = 'edit_message_' . $id;
$formToken = csrf_token($formTokenKey);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null, $formTokenKey)) {
        $error = 'Sesi formulir tidak valid. Silakan refresh halaman dan coba lagi.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $delay_days = (int) max(0, (int) ($_POST['delay_days'] ?? 0));
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $file_url = $message['file_url'];
        $delete_file_flag = isset($_POST['delete_file']);

        if ($title === '' || $content === '') {
            $error = 'Title dan content tidak boleh kosong';
        } elseif (mb_strlen($title) > 255) {
            $error = 'Title terlalu panjang. Maksimal 255 karakter.';
        } elseif ($delay_days > 365) {
            $error = 'Jeda hari maksimal 365.';
        } else {
            if ($delete_file_flag && !empty($file_url)) {
                delete_file($file_url);
                $file_url = null;
            }

            if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = upload_file($_FILES['media_file'], 'messages');

                if ($upload_result['success']) {
                    if (!empty($file_url)) {
                        delete_file($file_url);
                    }
                    $file_url = $upload_result['path'];
                } else {
                    $error = 'Error upload file: ' . $upload_result['message'];
                }
            }

            if ($error === '') {
                try {
                    $updateStmt = $pdo->prepare('UPDATE messages SET title = :title, content = :content, delay_days = :delay_days, file_url = :file_url, is_active = :is_active WHERE id = :id');
                    $updateStmt->bindValue(':title', $title, PDO::PARAM_STR);
                    $updateStmt->bindValue(':content', $content, PDO::PARAM_STR);
                    $updateStmt->bindValue(':delay_days', $delay_days, PDO::PARAM_INT);
                    $updateStmt->bindValue(':file_url', $file_url, $file_url === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $updateStmt->bindValue(':is_active', $is_active, PDO::PARAM_INT);
                    $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $updateStmt->execute();

                    $success = 'Perubahan berhasil disimpan';

                    $fetchStmt->execute();
                    $message = $fetchStmt->fetch();
                    $formToken = csrf_token($formTokenKey);
                } catch (PDOException $exception) {
                    log_security_event('Gagal memperbarui pesan: ' . $exception->getMessage());
                    $error = 'Terjadi kesalahan saat memperbarui data.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pesan - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-header">
        <h1>✏️ Edit Pesan</h1>
        <div class="admin-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="subscribers.php">Subscribers</a>
            <a href="add_message.php">Tambah Pesan</a>
            <a href="messages.php" class="nav-active">Daftar Pesan</a>
            <a href="logs.php">Log Pengiriman</a>
            <form method="POST" action="logout.php" class="logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo e($logoutToken); ?>">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="admin-container">
        <div class="content-section">
            <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo e($formToken); ?>">

                <div class="form-group">
                    <label for="title">Judul</label>
                    <input type="text" id="title" name="title" required maxlength="255" value="<?php echo e($message['title']); ?>">
                </div>
                <div class="form-group">
                    <label for="content">Isi Pesan</label>
                    <textarea id="content" name="content" rows="7" required><?php echo e($message['content']); ?></textarea>
                    <small>💡 Gunakan <strong>{nama}</strong> atau <strong>{name}</strong> untuk personalisasi</small>
                </div>
                
                <?php if (!empty($message['file_url'])): ?>
                <div class="form-group">
                    <label>File Media Saat Ini:</label>
                    <div class="current-file-preview">
                        <?php 
                        $ext = strtolower((string) pathinfo($message['file_url'], PATHINFO_EXTENSION));
                        $file_url_abs = '../' . ltrim((string) $message['file_url'], '/');
                        ?>
                        <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)): ?>
                            <img src="<?php echo e($file_url_abs); ?>" alt="Media saat ini" class="current-media-thumb">
                        <?php endif; ?>
                        <p>📎 <a href="<?php echo e($file_url_abs); ?>" target="_blank" rel="noopener noreferrer"><?php echo e(basename((string) $message['file_url'])); ?></a></p>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="delete_file" value="1"> Hapus file ini
                        </label>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="media_file">Upload File Media Baru (Opsional)</label>
                    <input type="file" id="media_file" name="media_file" accept="image/*,video/*,.pdf">
                    <small>📎 Upload gambar, video, atau PDF (maksimal 10MB). File lama akan diganti jika upload file baru.</small>
                </div>
                
                <div class="form-group">
                    <label for="delay_days">Jeda Hari</label>
                    <input type="number" id="delay_days" name="delay_days" min="0" max="365" value="<?php echo (int) $message['delay_days']; ?>">
                </div>
                <div class="form-group">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="is_active" value="1" <?php echo ((int) $message['is_active'] === 1) ? 'checked' : ''; ?>> Aktif
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">💾 Simpan</button>
                    <a class="btn-secondary" href="messages.php">❌ Kembali</a>
                </div>
            </form>
        </div>
    </div>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
