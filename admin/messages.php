<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'helpers.php';

require_admin_auth();

try {
    $messagesStmt = $pdo->prepare('SELECT id, title, content, delay_days, file_url, is_active, created_at FROM messages ORDER BY delay_days ASC, created_at DESC');
    $messagesStmt->execute();
    $messages = $messagesStmt->fetchAll();
} catch (PDOException $exception) {
    log_security_event('Gagal mengambil daftar pesan: ' . $exception->getMessage());
    $messages = [];
}

$logoutToken = csrf_token('logout');
$flashSuccess = $_SESSION['success'] ?? '';
$flashError = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
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
            <form method="POST" action="logout.php" class="logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo e($logoutToken); ?>">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="admin-container">
        <div class="content-section">
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?php echo e($flashSuccess); ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error"><?php echo e($flashError); ?></div>
            <?php endif; ?>

            <a href="add_message.php" class="btn-primary">➕ Tambah Pesan Baru</a>
            <p><strong>Total:</strong> <?php echo (int) count($messages); ?> pesan</p>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Judul</th>
                        <th>Isi Pesan</th>
                        <th>Media</th>
                        <th>Jeda (Hari)</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Belum ada pesan</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <?php $deleteToken = csrf_token('delete_message_' . $msg['id']); ?>
                            <tr>
                                <td><?php echo (int) $msg['id']; ?></td>
                                <td><?php echo e($msg['title']); ?></td>
                                <td><?php echo e(mb_substr($msg['content'], 0, 50)) . (mb_strlen($msg['content']) > 50 ? '…' : ''); ?></td>
                                <td>
                                    <?php if (!empty($msg['file_url'])): ?>
                                        <?php
                                        $ext = strtolower((string) pathinfo($msg['file_url'], PATHINFO_EXTENSION));
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                                            echo '🖼️ Gambar';
                                        } elseif (in_array($ext, ['mp4', 'avi', 'mpeg', 'mov'], true)) {
                                            echo '🎥 Video';
                                        } elseif ($ext === 'pdf') {
                                            echo '📄 PDF';
                                        } else {
                                            echo '📎 File';
                                        }
                                        ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int) $msg['delay_days']; ?> hari</td>
                                <td>
                                    <?php if ((int) $msg['is_active'] === 1): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e(date('d/m/Y H:i', strtotime((string) $msg['created_at']))); ?></td>
                                <td class="action-cell">
                                    <a class="btn-secondary" href="message_edit.php?id=<?php echo (int) $msg['id']; ?>">✏️ Edit</a>
                                    <form method="POST" action="message_delete.php" class="inline-form" data-confirm="Hapus pesan ini? Semua log terkait akan ikut terhapus.">
                                        <input type="hidden" name="id" value="<?php echo (int) $msg['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($deleteToken); ?>">
                                        <button type="submit" class="btn-danger">🗑️ Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
