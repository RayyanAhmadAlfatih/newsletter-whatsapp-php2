<?php
require_once 'db.php';

// Cek login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Ambil semua pesan
$stmt = $pdo->query("SELECT * FROM messages ORDER BY delay_days ASC, created_at DESC");
$messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pesan - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-header">
        <h1>📝 Daftar Pesan Otomatis</h1>
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
            <a href="add_message.php" class="btn-primary">➕ Tambah Pesan Baru</a>
            <p><strong>Total:</strong> <?php echo count($messages); ?> pesan</p>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Judul</th>
                        <th>Isi Pesan</th>
                        <th>Jeda (Hari)</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Belum ada pesan</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td><?php echo $msg['id']; ?></td>
                                <td><?php echo htmlspecialchars($msg['title']); ?></td>
                                <td><?php echo htmlspecialchars(mb_substr($msg['content'], 0, 50)) . '...'; ?></td>
                                <td><?php echo $msg['delay_days']; ?> hari</td>
                                <td>
                                    <?php if ($msg['is_active']): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?></td>
                                <td>
                                    <a class="btn-secondary" href="message_edit.php?id=<?php echo $msg['id']; ?>">Edit</a>
                                    <a class="btn-secondary" href="message_delete.php?id=<?php echo $msg['id']; ?>" onclick="return confirm('Hapus pesan ini? Semua log terkait akan ikut terhapus.');">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

