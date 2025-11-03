<?php
// Enable minimal error visibility while editing
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once 'db.php';

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

    if ($title === '' || $content === '') {
        $error = 'Title dan content tidak boleh kosong';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE messages SET title = ?, content = ?, delay_days = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$title, $content, $delay_days, $is_active, $id]);
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

            <form method="POST">
                <div class="form-group">
                    <label for="title">Judul</label>
                    <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($message['title']); ?>">
                </div>
                <div class="form-group">
                    <label for="content">Isi Pesan</label>
                    <textarea id="content" name="content" rows="7" required><?php echo htmlspecialchars($message['content']); ?></textarea>
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
                    <button type="submit" class="btn-primary">Simpan</button>
                    <a class="btn-secondary" href="messages.php">Kembali</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>


