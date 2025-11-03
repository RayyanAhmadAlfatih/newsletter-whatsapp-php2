<?php
// TEMP: enable error reporting for debugging dashboard blank page
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

require_once 'db.php';

// Cek login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Ambil statistik
$stats = [];

// Total subscribers
$stmt = $pdo->query("SELECT COUNT(*) as total FROM subscribers");
$stats['total_subscribers'] = $stmt->fetch()['total'];

// Total messages
$stmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
$stats['total_messages'] = $stmt->fetch()['total'];

// Total sent
$stmt = $pdo->query("SELECT COUNT(*) as total FROM message_logs WHERE status = 'sent'");
$stats['total_sent'] = $stmt->fetch()['total'];

// Total pending
$stmt = $pdo->query("SELECT COUNT(*) as total FROM message_logs WHERE status = 'pending'");
$stats['total_pending'] = $stmt->fetch()['total'];

// Total failed
$stmt = $pdo->query("SELECT COUNT(*) as total FROM message_logs WHERE status = 'failed'");
$stats['total_failed'] = $stmt->fetch()['total'];

// Ambil 10 subscriber terbaru
$stmt = $pdo->query("SELECT * FROM subscribers ORDER BY created_at DESC LIMIT 10");
$recent_subscribers = $stmt->fetchAll();
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
            <a href="?logout=1" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="admin-container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Subscribers</h3>
                <p class="stat-number"><?php echo $stats['total_subscribers']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Pesan</h3>
                <p class="stat-number"><?php echo $stats['total_messages']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Pesan Terkirim</h3>
                <p class="stat-number success"><?php echo $stats['total_sent']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <p class="stat-number warning"><?php echo $stats['total_pending']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Gagal</h3>
                <p class="stat-number error"><?php echo $stats['total_failed']; ?></p>
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
                                <td><?php echo $sub['id']; ?></td>
                                <td><?php echo htmlspecialchars($sub['name']); ?></td>
                                <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                <td><?php echo htmlspecialchars($sub['phone']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sub['created_at'])); ?></td>
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
                <a href="send_auto.php?run=1" class="btn-secondary" onclick="return confirm('Jalankan pengiriman otomatis sekarang?')">🚀 Jalankan Pengiriman</a>
                <a href="logs.php" class="btn-secondary">📋 Lihat Log</a>
            </div>
        </div>
    </div>
</body>
</html>

