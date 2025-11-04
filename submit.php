<?php
require_once 'admin/db.php';
require_once 'admin/helpers.php';

// Cek jika form sudah di-submit
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Ambil data dari form
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// Validasi
$errors = [];

if (empty($name) || strlen($name) < 3) {
    $errors[] = "Nama tidak boleh kosong (minimal 3 karakter)";
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Email tidak valid";
}

// Validasi nomor WhatsApp dengan helper
$validated_phone = validate_whatsapp_number($phone);
if (!$validated_phone) {
    $errors[] = "Nomor WhatsApp tidak valid. Gunakan format 08xx atau 628xx";
}

// Jika ada error, redirect kembali
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header('Location: index.php');
    exit;
}

// Gunakan nomor yang sudah divalidasi dan dinormalisasi
$phone = $validated_phone;

try {
    // Cek apakah email atau phone sudah terdaftar
    $stmt = $pdo->prepare("SELECT id FROM subscribers WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $phone]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Email atau nomor WhatsApp sudah terdaftar";
        header('Location: index.php');
        exit;
    }

    // Insert subscriber baru
    $stmt = $pdo->prepare("INSERT INTO subscribers (name, email, phone) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $phone]);
    
    $subscriberId = $pdo->lastInsertId();

    // Buat log pesan untuk semua pesan aktif berdasarkan delay_days
    $stmt = $pdo->prepare("SELECT id, delay_days FROM messages WHERE is_active = 1 ORDER BY delay_days ASC");
    $stmt->execute();
    $messages = $stmt->fetchAll();

    foreach ($messages as $message) {
        $stmtLog = $pdo->prepare("INSERT INTO message_logs (subscriber_id, message_id, status) VALUES (?, ?, 'pending')");
        $stmtLog->execute([$subscriberId, $message['id']]);
    }

    $_SESSION['success'] = "Pendaftaran berhasil! Kami akan mengirimkan pesan ke WhatsApp Anda.";
    
    // Auto-kirim pesan dengan delay 0 hari (hari pertama)
    $stmt = $pdo->prepare("
        SELECT ml.id as log_id, ml.subscriber_id, ml.message_id, s.phone, s.name as subscriber_name, m.content, m.file_url
        FROM message_logs ml
        JOIN subscribers s ON ml.subscriber_id = s.id
        JOIN messages m ON ml.message_id = m.id
        WHERE ml.subscriber_id = ? AND m.delay_days = 0 AND ml.status = 'pending'
    ");
    $stmt->execute([$subscriberId]);
    $messagesToSend = $stmt->fetchAll();

    if (!empty($messagesToSend)) {
        $fonnteApiKey = defined('FONNTE_API_KEY') ? FONNTE_API_KEY : '';
        if (empty($fonnteApiKey)) {
            $missingKeyMessage = 'Fonnte API key belum dikonfigurasi';
            $stmtUpd = $pdo->prepare("UPDATE message_logs SET status = 'failed', error_message = ? WHERE id = ?");
            foreach ($messagesToSend as $msg) {
                $stmtUpd->execute([$missingKeyMessage, $msg['log_id']]);
            }
        } else {
            $stmtUpd = $pdo->prepare("UPDATE message_logs SET status = ?, sent_at = NOW(), error_message = ? WHERE id = ?");
            foreach ($messagesToSend as $msg) {
                $content = str_replace(['{nama}', '{name}'], $msg['subscriber_name'], $msg['content']);
                $phoneWA = normalize_phone($msg['phone']);
                
                // Build post fields
                $postfields = [
                    'target' => $phoneWA,
                    'message' => $content,
                    'countryCode' => '62'
                ];
                
                // Tambahkan URL media jika ada
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
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => $postfields,
                    CURLOPT_HTTPHEADER => ['Authorization: ' . $fonnteApiKey],
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                // Update log
                $result = json_decode($response, true);
                $statusFlag = null;
                if (is_array($result)) {
                    if (isset($result['status'])) { $statusFlag = $result['status']; }
                    elseif (isset($result['message'])) { $statusFlag = $result['message']; }
                }
                
                $isSuccess = ($httpCode === 200) && (
                    $statusFlag === 'success' || $statusFlag === true || $statusFlag === 'ok'
                );
                
                $errorMsg = $isSuccess ? null : (is_array($result) ? json_encode($result) : 'HTTP ' . $httpCode);
                
                $stmtUpd->execute([
                    $isSuccess ? 'sent' : 'failed',
                    $errorMsg,
                    $msg['log_id']
                ]);
                
                usleep(200000); // 0.2s delay antar pesan
            }
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
}

header('Location: index.php');
exit;
?>

