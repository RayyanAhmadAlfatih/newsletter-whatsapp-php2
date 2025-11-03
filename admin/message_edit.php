<?php
// Enable minimal error visibility while editing
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once 'db.php';
require_once 'helpers.php';

// Cek login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: messages.php');
    exit;
}

// Ambil data pesan
$stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message) {
    header('Location: messages.php');
    exit;
}

$success = '';
$error = '';

// Update data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $delay_days = max(0, (int)($_POST['delay_days'] ?? 0));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $file_url = $message['file_url']; // Keep existing file
    $delete_file_flag = isset($_POST['delete_file']) ? true : false;

    if ($title === '' || $content === '') {
        $error = 'Title dan content tidak boleh kosong';
    } else {
        // Handle file deletion
        if ($delete_file_flag && !empty($file_url)) {
            delete_file($file_url);
            $file_url = null;
        }
        
        // Handle new file upload
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = upload_file($_FILES['media_file'], 'messages');
            
            if ($upload_result['success']) {
                // Delete old file if exists
                if (!empty($file_url)) {
                    delete_file($file_url);
                }
                $file_url = $upload_result['path'];
            } else {
                $error = 'Error upload file: ' . $upload_result['message'];
            }
        }
        
        if (empty($error)) {
            try {
                $stmt = $pdo->prepare('UPDATE messages SET title = ?, content = ?, delay_days = ?, file_url = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$title, $content, $delay_days, $file_url, $is_active, $id]);
                $success = 'Perubahan berhasil disimpan';
                // Refresh data
                $stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
                $stmt->execute([$id]);
                $message = $stmt->fetch();
            } catch (PDOException $e) {
                $error = 'Gagal menyimpan: ' . $e->getMessage();
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
    <style>.form-actions{margin-top:10px}</style>
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
            <a href="dashboard.php?logout=1" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="admin-container">
        <div class="content-section">
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Judul</label>
                    <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($message['title']); ?>">
                </div>
                <div class="form-group">
                    <label for="content">Isi Pesan</label>
                    <textarea id="content" name="content" rows="7" required><?php echo htmlspecialchars($message['content']); ?></textarea>
                    <small>💡 Gunakan <strong>{nama}</strong> atau <strong>{name}</strong> untuk personalisasi</small>
                </div>
                
                <?php if (!empty($message['file_url'])): ?>
                <div class="form-group">
                    <label>File Media Saat Ini:</label>
                    <div style="padding: 10px; background: #f5f5f5; border-radius: 5px; margin-bottom: 10px;">
                        <?php 
                        $ext = strtolower(pathinfo($message['file_url'], PATHINFO_EXTENSION));
                        $file_url_abs = '../' . $message['file_url'];
                        ?>
                        <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                            <img src="<?php echo htmlspecialchars($file_url_abs); ?>" style="max-width: 200px; max-height: 200px; display: block; margin-bottom: 10px;">
                        <?php endif; ?>
                        <p>📎 <a href="<?php echo htmlspecialchars($file_url_abs); ?>" target="_blank"><?php echo basename($message['file_url']); ?></a></p>
                        <label>
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
                    <input type="number" id="delay_days" name="delay_days" min="0" value="<?php echo (int)$message['delay_days']; ?>">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?php echo $message['is_active'] ? 'checked' : ''; ?>> Aktif
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">💾 Simpan</button>
                    <a class="btn-secondary" href="messages.php">❌ Kembali</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>


