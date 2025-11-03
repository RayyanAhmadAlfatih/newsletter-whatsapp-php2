<?php
require_once 'db.php';
require_once 'helpers.php';

// Cek login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

// Proses tambah pesan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $delay_days = intval($_POST['delay_days'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $file_url = null;
    
    if (empty($title) || empty($content)) {
        $error = 'Title dan content tidak boleh kosong!';
    } else {
        // Handle file upload jika ada
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = upload_file($_FILES['media_file'], 'messages');
            
            if ($upload_result['success']) {
                $file_url = $upload_result['path'];
            } else {
                $error = 'Error upload file: ' . $upload_result['message'];
            }
        }
        
        // Jika tidak ada error upload, simpan ke database
        if (empty($error)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO messages (title, content, delay_days, file_url, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $delay_days, $file_url, $is_active]);
                $success = 'Pesan berhasil ditambahkan!';
                
                // Reset form
                $_POST = [];
            } catch (PDOException $e) {
                // Jika gagal simpan ke database, hapus file yang sudah diupload
                if ($file_url) {
                    delete_file($file_url);
                }
                $error = 'Error: ' . $e->getMessage();
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
            <a href="dashboard.php?logout=1" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="admin-container">
        <div class="content-section">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Judul Pesan <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                           placeholder="Contoh: Selamat Datang">
                </div>
                
                <div class="form-group">
                    <label for="content">Isi Pesan <span class="required">*</span></label>
                    <textarea id="content" name="content" rows="6" required 
                              placeholder="Masukkan isi pesan yang akan dikirim ke WhatsApp. Gunakan {nama} atau {name} untuk personalisasi."><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                    <small>💡 Tips: Gunakan <strong>{nama}</strong> atau <strong>{name}</strong> untuk menyapa subscriber secara personal</small>
                </div>
                
                <div class="form-group">
                    <label for="media_file">File Media (Opsional)</label>
                    <input type="file" id="media_file" name="media_file" accept="image/*,video/*,.pdf">
                    <small>📎 Upload gambar, video, atau PDF (maksimal 10MB). File akan dikirim bersama pesan teks.</small>
                </div>
                
                <div class="form-group">
                    <label for="delay_days">Jeda Hari (setelah pendaftaran) <span class="required">*</span></label>
                    <input type="number" id="delay_days" name="delay_days" required min="0" 
                           value="<?php echo htmlspecialchars($_POST['delay_days'] ?? '0'); ?>">
                    <small>0 = hari pertama, 1 = 1 hari setelah daftar, dst.</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" checked>
                        Aktif (pesan akan dikirim)
                    </label>
                </div>
                
                <button type="submit" class="btn-primary">💾 Simpan Pesan</button>
                <a href="messages.php" class="btn-secondary">❌ Batal</a>
            </form>
        </div>
    </div>
</body>
</html>

