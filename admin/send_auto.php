<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'helpers.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    require_admin_auth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Metode tidak diizinkan.');
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? null, 'send_auto')) {
        log_security_event('CSRF tidak valid untuk send_auto dari IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        http_response_code(400);
        exit('Permintaan tidak valid atau kedaluwarsa.');
    }
}

/**
 * Fungsi untuk mengirim pesan WhatsApp via Fonnte API
 * Support teks + media (gambar, video, pdf)
 */
function sendWhatsAppMessage(string $phone, string $message, string $apiKey, ?string $fileUrl = null): array
{
    $url = FONNTE_API_URL;
    $postfields = [
        'target' => $phone,
        'message' => $message,
        'countryCode' => '62',
    ];
    
    if (!empty($fileUrl)) {
        if (!preg_match('/^https?:\/\//i', $fileUrl)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
            if (PHP_SAPI === 'cli' && defined('BASE_URL')) {
                $baseUrl = BASE_URL;
            }
            $fileUrl = rtrim($baseUrl, '/') . '/' . ltrim($fileUrl, '/');
        }
        $postfields['url'] = $fileUrl;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postfields,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $apiKey,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $result = json_decode((string) $response, true);

    $statusFlag = null;
    if (is_array($result)) {
        if (isset($result['status'])) {
            $statusFlag = $result['status'];
        } elseif (isset($result['message'])) {
            $statusFlag = $result['message'];
        } elseif (isset($result['detail']['status'])) {
            $statusFlag = $result['detail']['status'];
        }
    }

    $isSuccess = ($httpCode === 200) && in_array($statusFlag, [true, 'true', 'success', 'ok'], true);

    return [
        'success' => $isSuccess,
        'response' => $result ?? $response,
        'http_code' => $httpCode,
        'error' => $err,
    ];
}

try {
    $stmt = $pdo->prepare(
        'SELECT ml.id, ml.status, ml.sent_at, ml.error_message, 
                s.phone, s.name as subscriber_name, s.created_at as subscriber_created_at,
                m.content, m.title, m.delay_days, m.file_url
         FROM message_logs ml
         JOIN subscribers s ON ml.subscriber_id = s.id
         JOIN messages m ON ml.message_id = m.id
         WHERE ml.status = :status
         ORDER BY ml.id ASC'
    );
    $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);
    $stmt->execute();
    $pendingLogs = $stmt->fetchAll();
} catch (PDOException $exception) {
    log_security_event('Gagal mengambil data pending logs: ' . $exception->getMessage());
    $pendingLogs = [];
}

$results = [
    'processed' => 0,
    'sent' => 0,
    'failed' => 0,
    'skipped' => 0,
    'details' => [],
    'message' => null,
];

$missingApiKey = empty(FONNTE_API_KEY);
$missingApiKeyMessage = 'Fonnte API key belum dikonfigurasi';
$missingApiKeyStmt = null;
if ($missingApiKey && $pdo instanceof PDO) {
    $missingApiKeyStmt = $pdo->prepare(
        'UPDATE message_logs SET status = :status, error_message = :error WHERE id = :id'
    );
}

foreach ($pendingLogs as $log) {
    $results['processed']++;
    
    $subscriberCreatedAt = new DateTime((string) $log['subscriber_created_at']);
    $targetDate = (clone $subscriberCreatedAt);
    $targetDate->modify('+' . (int) $log['delay_days'] . ' days');
    $targetDate->setTime(0, 0, 0);
    
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($today < $targetDate) {
        $results['skipped']++;
        $results['details'][] = [
            'subscriber' => $log['subscriber_name'],
            'phone' => $log['phone'],
            'message' => $log['title'],
            'status' => 'skipped',
            'reason' => 'Belum waktunya (target: ' . $targetDate->format('Y-m-d') . ')',
        ];
        continue;
    }

    if ($missingApiKey && $missingApiKeyStmt) {
        $missingApiKeyStmt->execute([
            ':status' => 'failed',
            ':error' => $missingApiKeyMessage,
            ':id' => $log['id'],
        ]);
        $results['failed']++;
        $results['details'][] = [
            'subscriber' => $log['subscriber_name'],
            'phone' => $log['phone'],
            'message' => $log['title'],
            'status' => 'failed',
            'error' => $missingApiKeyMessage,
        ];
        continue;
    }
    
    $phone = $log['phone'];
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) !== '62') {
        $phone = '62' . preg_replace('/^\+?/', '', $phone);
    }
    
    $messageContent = str_replace(
        ['{nama}', '{name}'],
        $log['subscriber_name'],
        $log['content']
    );
    
    $sendResult = sendWhatsAppMessage($phone, $messageContent, FONNTE_API_KEY, $log['file_url']);
    
    if ($sendResult['success']) {
        try {
            $updateStmt = $pdo->prepare('UPDATE message_logs SET status = :status, sent_at = NOW(), error_message = NULL WHERE id = :id');
            $updateStmt->execute([
                ':status' => 'sent',
                ':id' => $log['id'],
            ]);
        } catch (PDOException $exception) {
            log_security_event('Gagal memperbarui status log setelah sukses kirim: ' . $exception->getMessage());
        }
        
        $results['sent']++;
        $results['details'][] = [
            'subscriber' => $log['subscriber_name'],
            'phone' => $log['phone'],
            'message' => $log['title'],
            'status' => 'sent',
        ];
    } else {
        $raw = $sendResult['response'];
        $msgFromApi = null;
        if (is_array($raw)) {
            $msgFromApi = $raw['message'] ?? ($raw['detail']['message'] ?? null);
        }
        $errorMsg = $msgFromApi ?: ($sendResult['error'] ?: ('HTTP ' . $sendResult['http_code']));
        $errorMsg = substr((string) $errorMsg, 0, 255);
        $responseSnippet = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
        $responseSnippet = $responseSnippet ? substr($responseSnippet, 0, 500) : '';
        
        try {
            $updateStmt = $pdo->prepare('UPDATE message_logs SET status = :status, error_message = :error WHERE id = :id');
            $updateStmt->execute([
                ':status' => 'failed',
                ':error' => $errorMsg . ($responseSnippet ? (' :: ' . $responseSnippet) : ''),
                ':id' => $log['id'],
            ]);
        } catch (PDOException $exception) {
            log_security_event('Gagal memperbarui status log setelah gagal kirim: ' . $exception->getMessage());
        }
        
        $results['failed']++;
        $results['details'][] = [
            'subscriber' => $log['subscriber_name'],
            'phone' => $log['phone'],
            'message' => $log['title'],
            'status' => 'failed',
            'error' => $errorMsg,
        ];
    }
    
    usleep(500000);
}

if ($missingApiKey) {
    $results['message'] = $missingApiKeyMessage;
}

if ($isCli) {
    if (!empty($results['message'])) {
        echo $results['message'] . "\n";
    }
    echo "Pengiriman selesai:\n";
    echo "- Diproses: {$results['processed']}\n";
    echo "- Terkirim: {$results['sent']}\n";
    echo "- Gagal: {$results['failed']}\n";
    echo "- Dilewati: {$results['skipped']}\n";
    exit;
}

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
