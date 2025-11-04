<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'helpers.php';

require_admin_auth();

$stats = [
    'total_subscribers' => 0,
    'total_messages' => 0,
    'total_sent' => 0,
    'total_pending' => 0,
    'total_failed' => 0,
];

try {
    $countQuery = $pdo->prepare('SELECT COUNT(*) AS total FROM subscribers');
    $countQuery->execute();
    $stats['total_subscribers'] = (int) $countQuery->fetchColumn();

    $countQuery = $pdo->prepare('SELECT COUNT(*) AS total FROM messages');
    $countQuery->execute();
    $stats['total_messages'] = (int) $countQuery->fetchColumn();

    $statusQuery = $pdo->prepare("SELECT COUNT(*) AS total FROM message_logs WHERE status = :status");

    foreach (['sent' => 'total_sent', 'pending' => 'total_pending', 'failed' => 'total_failed'] as $status => $key) {
        $statusQuery->execute([':status' => $status]);
        $stats[$key] = (int) $statusQuery->fetchColumn();
    }

    $recentStmt = $pdo->prepare('SELECT id, name, email, phone, created_at FROM subscribers ORDER BY created_at DESC LIMIT :limit');
    $recentStmt->bindValue(':limit', 10, PDO::PARAM_INT);
    $recentStmt->execute();
    $recent_subscribers = $recentStmt->fetchAll();
} catch (PDOException $exception) {
    log_security_event('Gagal mengambil statistik dashboard: ' . $exception->getMessage());
    $recent_subscribers = [];
}

$logoutToken = csrf_token('logout');
$sendAutoToken = csrf_token('send_auto');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Newsletter WhatsApp</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-header">
        <h1>📊 Admin Dashboard</h1>
        <div class="admin-nav">
            <a href="dashboard.php" class="nav-active">Dashboard</a>
            <a href="subscribers.php">Subscribers</a>
            <a href="add_message.php">Tambah Pesan</a>
            <a href="messages.php">Daftar Pesan</a>
            <a href="logs.php">Log Pengiriman</a>
            <form method="POST" action="logout.php" class="logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo e($logoutToken); ?>">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="admin-container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Subscribers</h3>
                <p class="stat-number"><?php echo (int) $stats['total_subscribers']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Pesan</h3>
                <p class="stat-number"><?php echo (int) $stats['total_messages']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Pesan Terkirim</h3>
                <p class="stat-number success"><?php echo (int) $stats['total_sent']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <p class="stat-number warning"><?php echo (int) $stats['total_pending']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Gagal</h3>
                <p class="stat-number error"><?php echo (int) $stats['total_failed']; ?></p>
            </div>
        </div>

        <div class="content-section">
            <h2>Subscriber Terbaru</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>WhatsApp</th>
                        <th>Tanggal Daftar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_subscribers)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Belum ada subscriber</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_subscribers as $sub): ?>
                            <tr>
                                <td><?php echo (int) $sub['id']; ?></td>
                                <td><?php echo e($sub['name']); ?></td>
                                <td><?php echo e($sub['email']); ?></td>
                                <td><?php echo e($sub['phone']); ?></td>
                                <td><?php echo e(date('d/m/Y H:i', strtotime((string) $sub['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="content-section">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="add_message.php" class="btn-primary">➕ Tambah Pesan Baru</a>
                <form method="POST" action="send_auto.php" class="inline-form" data-confirm="Jalankan pengiriman otomatis sekarang?" data-async="true">
                    <input type="hidden" name="csrf_token" value="<?php echo e($sendAutoToken); ?>">
                    <button type="submit" class="btn-secondary">🚀 Jalankan Pengiriman</button>
                </form>
                <a href="logs.php" class="btn-secondary">📋 Lihat Log</a>
            </div>
        </div>
    </div>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
