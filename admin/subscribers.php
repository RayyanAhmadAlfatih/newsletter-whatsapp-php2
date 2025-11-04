<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'helpers.php';

require_admin_auth();

$items_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

try {
    $countStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM subscribers');
    $countStmt->execute();
    $total_subscribers = (int) $countStmt->fetchColumn();
} catch (PDOException $exception) {
    log_security_event('Gagal menghitung subscribers: ' . $exception->getMessage());
    $total_subscribers = 0;
}

$total_pages = $total_subscribers === 0 ? 1 : (int) ceil($total_subscribers / $items_per_page);

try {
    $stmt = $pdo->prepare('SELECT id, name, email, phone, created_at FROM subscribers ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $subscribers = $stmt->fetchAll();
} catch (PDOException $exception) {
    log_security_event('Gagal mengambil data subscribers: ' . $exception->getMessage());
    $subscribers = [];
}

$logoutToken = csrf_token('logout');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Subscribers - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-header">
        <h1>👥 Daftar Subscribers</h1>
        <div class="admin-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="subscribers.php" class="nav-active">Subscribers</a>
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
        <div class="content-section">
            <p><strong>Total:</strong> <?php echo (int) $total_subscribers; ?> subscribers | Halaman <?php echo (int) $current_page; ?> dari <?php echo (int) $total_pages; ?></p>
            
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
                    <?php if (empty($subscribers)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Belum ada subscriber</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subscribers as $sub): ?>
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
            
            <?php echo generate_pagination($current_page, $total_pages, '?'); ?>
        </div>
    </div>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
