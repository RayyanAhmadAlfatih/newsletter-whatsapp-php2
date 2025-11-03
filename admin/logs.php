<?php
require_once 'db.php';

// Cek login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Filter status
$status_filter = $_GET['status'] ?? 'all';

// Query logs dengan join
$sql = "SELECT ml.*, s.name as subscriber_name, s.phone, m.title as message_title 
        FROM message_logs ml
        JOIN subscribers s ON ml.subscriber_id = s.id
        JOIN messages m ON ml.message_id = m.id";

if ($status_filter !== 'all') {
    $sql .= " WHERE ml.status = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status_filter]);
} else {
    $stmt = $pdo->query($sql);
}

$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Pengiriman - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-header">
        <h1>📋 Log Pengiriman Pesan</h1>
        <div class="admin-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="subscribers.php">Subscribers</a>
            <a href="add_message.php">Tambah Pesan</a>
            <a href="messages.php">Daftar Pesan</a>
            <a href="logs.php" class="nav-active">Log Pengiriman</a>
            <a href="dashboard.php?logout=1" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="admin-container">
        <div class="content-section">
            <div class="filter-section">
                <strong>Filter Status:</strong>
                <a href="?status=all" class="filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">Semua</a>
                <a href="?status=pending" class="filter-btn <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?status=sent" class="filter-btn <?php echo $status_filter === 'sent' ? 'active' : ''; ?>">Terkirim</a>
                <a href="?status=failed" class="filter-btn <?php echo $status_filter === 'failed' ? 'active' : ''; ?>">Gagal</a>
            </div>
            
            <p><strong>Total:</strong> <?php echo count($logs); ?> log</p>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subscriber</th>
                        <th>Nomor WA</th>
                        <th>Pesan</th>
                        <th>Status</th>
                        <th>Waktu Kirim</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Belum ada log</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td><?php echo htmlspecialchars($log['subscriber_name']); ?></td>
                                <td><?php echo htmlspecialchars($log['phone']); ?></td>
                                <td><?php echo htmlspecialchars($log['message_title']); ?></td>
                                <td>
                                    <?php if ($log['status'] === 'sent'): ?>
                                        <span class="badge badge-success">Terkirim</span>
                                    <?php elseif ($log['status'] === 'failed'): ?>
                                        <span class="badge badge-error">Gagal</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $log['sent_at'] ? date('d/m/Y H:i', strtotime($log['sent_at'])) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo $log['error_message'] ? htmlspecialchars($log['error_message']) : '-'; ?>
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

