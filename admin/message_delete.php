<?php
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

try {
    // Hapus message_logs terkait (akan ikut terhapus kalau foreign key ON DELETE CASCADE aktif)
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
    $stmt->execute([$id]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['error'] = 'Gagal menghapus: ' . $e->getMessage();
}

header('Location: messages.php');
exit;


