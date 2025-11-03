<?php
require_once 'db.php';
require_once 'helpers.php';

// Cek login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Pagination
$items_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Hitung total subscribers
$stmt = $pdo->query("SELECT COUNT(*) as total FROM subscribers");
$total_subscribers = $stmt->fetch()['total'];
$total_pages = ceil($total_subscribers / $items_per_page);

// Ambil subscribers dengan pagination
$stmt = $pdo->prepare("SELECT * FROM subscribers ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$items_per_page, $offset]);
$subscribers = $stmt->fetchAll();
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
            <a href="dashboard.php?logout=1" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="admin-container">
        <div class="content-section">
            <p><strong>Total:</strong> <?php echo $total_subscribers; ?> subscribers | Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?></p>
            
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
            
            <?php echo generate_pagination($current_page, $total_pages, '?'); ?>
        </div>
    </div>
</body>
</html>

