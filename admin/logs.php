<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'helpers.php';

require_admin_auth();

$allowedStatuses = ['all', 'pending', 'sent', 'failed'];
$status_filter = $_GET['status'] ?? 'all';
if (!in_array($status_filter, $allowedStatuses, true)) {
    $status_filter = 'all';
}

$items_per_page = 50;
$current_page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

$count_sql = <<<SQL
SELECT COUNT(*) as total
FROM message_logs ml
JOIN subscribers s ON ml.subscriber_id = s.id
JOIN messages m ON ml.message_id = m.id
SQL;

try {
    if ($status_filter !== 'all') {
        $countQuery = $pdo->prepare($count_sql . ' WHERE ml.status = :status');
        $countQuery->bindValue(':status', $status_filter, PDO::PARAM_STR);
    } else {
        $countQuery = $pdo->prepare($count_sql);
    }
    $countQuery->execute();
    $total_logs = (int) $countQuery->fetchColumn();
} catch (PDOException $exception) {
    log_security_event('Gagal menghitung total log: ' . $exception->getMessage());
    $total_logs = 0;
}

$total_pages = $total_logs === 0 ? 1 : (int) ceil($total_logs / $items_per_page);

$sql = <<<SQL
SELECT ml.id,
       ml.status,
       ml.sent_at,
       ml.error_message,
       s.name AS subscriber_name,
       s.phone,
       m.title AS message_title
FROM message_logs ml
JOIN subscribers s ON ml.subscriber_id = s.id
JOIN messages m ON ml.message_id = m.id
SQL;

if ($status_filter !== 'all') {
    $sql .= ' WHERE ml.status = :status';
}

$sql .= ' ORDER BY ml.id DESC LIMIT :limit OFFSET :offset';

try {
    $stmt = $pdo->prepare($sql);
    if ($status_filter !== 'all') {
        $stmt->bindValue(':status', $status_filter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (PDOException $exception) {
    log_security_event('Gagal mengambil data log: ' . $exception->getMessage());
    $logs = [];
}

$logoutToken = csrf_token('logout');
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
            <form method="POST" action="logout.php" class="logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo e($logoutToken); ?>">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
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
            
            <p><strong>Total:</strong> <?php echo (int) $total_logs; ?> log | Halaman <?php echo (int) $current_page; ?> dari <?php echo (int) $total_pages; ?></p>
            
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
                                <td><?php echo (int) $log['id']; ?></td>
                                <td><?php echo e($log['subscriber_name']); ?></td>
                                <td><?php echo e($log['phone']); ?></td>
                                <td><?php echo e($log['message_title']); ?></td>
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
                                    <?php echo $log['sent_at'] ? e(date('d/m/Y H:i', strtotime((string) $log['sent_at']))) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo $log['error_message'] ? e($log['error_message']) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php 
            $base_url = '?status=' . urlencode($status_filter);
            echo generate_pagination($current_page, $total_pages, $base_url);
            ?>
        </div>
    </div>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
