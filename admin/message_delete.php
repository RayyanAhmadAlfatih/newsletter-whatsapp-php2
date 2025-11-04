<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'helpers.php';

require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: messages.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'Permintaan tidak valid.';
    header('Location: messages.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null, 'delete_message_' . $id)) {
    log_security_event('CSRF tidak valid saat menghapus pesan ID ' . $id . ' dari IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $_SESSION['error'] = 'Permintaan tidak valid atau kedaluwarsa.';
    header('Location: messages.php');
    exit;
}

try {
    $pdo->beginTransaction();
    $deleteStmt = $pdo->prepare('DELETE FROM messages WHERE id = :id');
    $deleteStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $deleteStmt->execute();
    $pdo->commit();
    $_SESSION['success'] = 'Pesan berhasil dihapus.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_security_event('Gagal menghapus pesan ID ' . $id . ': ' . $exception->getMessage());
    $_SESSION['error'] = 'Gagal menghapus pesan.';
}

header('Location: messages.php');
exit;
