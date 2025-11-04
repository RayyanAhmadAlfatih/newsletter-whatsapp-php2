<?php
declare(strict_types=1);

require_once __DIR__ . '/admin/db.php';
require_once __DIR__ . '/admin/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null, 'public_register')) {
    log_security_event('Percobaan submit dengan CSRF tidak valid dari IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $_SESSION['error'] = 'Permintaan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    header('Location: index.php');
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));

$errors = [];

if ($name === '' || mb_strlen($name) < 3) {
    $errors[] = 'Nama tidak boleh kosong (minimal 3 karakter)';
} elseif (mb_strlen($name) > 120) {
    $errors[] = 'Nama maksimal 120 karakter';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email tidak valid';
} elseif (mb_strlen($email) > 190) {
    $errors[] = 'Email terlalu panjang (maksimal 190 karakter)';
}

$validated_phone = validate_whatsapp_number($phone);
if (!$validated_phone) {
    $errors[] = 'Nomor WhatsApp tidak valid. Gunakan format 08xx atau 628xx';
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header('Location: index.php');
    exit;
}

$phone = $validated_phone;

try {
    $existsStmt = $pdo->prepare('SELECT id FROM subscribers WHERE email = :email OR phone = :phone');
    $existsStmt->execute([
        ':email' => $email,
        ':phone' => $phone,
    ]);

    if ($existsStmt->fetch()) {
        $_SESSION['error'] = 'Email atau nomor WhatsApp sudah terdaftar';
        header('Location: index.php');
        exit;
    }

    $insertSubscriber = $pdo->prepare('INSERT INTO subscribers (name, email, phone) VALUES (:name, :email, :phone)');
    $insertSubscriber->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
    ]);

    $subscriberId = (int) $pdo->lastInsertId();

    $messagesStmt = $pdo->prepare('SELECT id, delay_days FROM messages WHERE is_active = 1 ORDER BY delay_days ASC');
    $messagesStmt->execute();
    $messages = $messagesStmt->fetchAll();

    $logInsertStmt = $pdo->prepare('INSERT INTO message_logs (subscriber_id, message_id, status) VALUES (:subscriber_id, :message_id, :status)');

    foreach ($messages as $message) {
        $logInsertStmt->execute([
            ':subscriber_id' => $subscriberId,
            ':message_id' => $message['id'],
            ':status' => 'pending',
        ]);
    }

    $_SESSION['success'] = 'Pendaftaran berhasil! Kami akan mengirimkan pesan ke WhatsApp Anda.';
    
    $autoSendStmt = $pdo->prepare(
        'SELECT ml.id as log_id, ml.subscriber_id, ml.message_id, s.phone, s.name as subscriber_name, m.content, m.file_url
         FROM message_logs ml
         JOIN subscribers s ON ml.subscriber_id = s.id
         JOIN messages m ON ml.message_id = m.id
         WHERE ml.subscriber_id = :subscriber_id AND m.delay_days = 0 AND ml.status = :status'
    );
    $autoSendStmt->execute([
        ':subscriber_id' => $subscriberId,
        ':status' => 'pending',
    ]);
    $messagesToSend = $autoSendStmt->fetchAll();

    if (!empty($messagesToSend)) {
        if (empty(FONNTE_API_KEY)) {
            $missingKeyMessage = 'Fonnte API key belum dikonfigurasi';
            $updateStmt = $pdo->prepare('UPDATE message_logs SET status = :status, error_message = :error WHERE id = :id');
            foreach ($messagesToSend as $msg) {
                $updateStmt->execute([
                    ':status' => 'failed',
                    ':error' => $missingKeyMessage,
                    ':id' => $msg['log_id'],
                ]);
            }
        } else {
            $updateStmt = $pdo->prepare('UPDATE message_logs SET status = :status, sent_at = NOW(), error_message = :error WHERE id = :id');
            foreach ($messagesToSend as $msg) {
                $content = str_replace(['{nama}', '{name}'], $msg['subscriber_name'], $msg['content']);
                $phoneWA = normalize_phone($msg['phone']);
                
                $postfields = [
                    'target' => $phoneWA,
                    'message' => $content,
                    'countryCode' => '62',
                ];
                
                if (!empty($msg['file_url'])) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $baseUrl = $protocol . '://' . $host;
                    $fileUrl = rtrim($baseUrl, '/') . '/' . ltrim($msg['file_url'], '/');
                    $postfields['url'] = $fileUrl;
                }
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => FONNTE_API_URL,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => $postfields,
                    CURLOPT_HTTPHEADER => ['Authorization: ' . FONNTE_API_KEY],
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                $result = json_decode((string) $response, true);
                $statusFlag = null;
                if (is_array($result)) {
                    if (isset($result['status'])) { $statusFlag = $result['status']; }
                    elseif (isset($result['message'])) { $statusFlag = $result['message']; }
                }
                
                $isSuccess = ($httpCode === 200) && in_array($statusFlag, ['success', true, 'true', 'ok'], true);
                $errorMsg = $isSuccess ? null : (is_array($result) ? json_encode($result) : ($curlError ?: 'HTTP ' . $httpCode));
                if (!$isSuccess && $errorMsg !== null) {
                    $errorMsg = substr((string) $errorMsg, 0, 255);
                }
                
                $updateStmt->execute([
                    ':status' => $isSuccess ? 'sent' : 'failed',
                    ':error' => $errorMsg,
                    ':id' => $msg['log_id'],
                ]);
                
                usleep(200000);
            }
        }
    }
} catch (PDOException $exception) {
    log_security_event('Kesalahan database saat pendaftaran: ' . $exception->getMessage());
    $_SESSION['error'] = 'Terjadi kesalahan internal. Silakan coba lagi nanti.';
}

header('Location: index.php');
exit;
