<?php
require_once 'db.php';

// Cek login (optional, bisa juga dijalankan via cron tanpa login)
// Jika diakses dari browser, perlu login
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    header('Location: index.php');
    exit;
}

/**
 * Fungsi untuk mengirim pesan WhatsApp via Fonnte API
 * Support teks + media (gambar, video, pdf)
 */
function sendWhatsAppMessage($phone, $message, $apiKey, $fileUrl = null) {
    $url = FONNTE_API_URL;
    // Fonnte expects multipart form fields, not raw JSON
    $postfields = [
        'target' => $phone,
        'message' => $message,
        'countryCode' => '62', // optional, keep consistent
    ];
    
    // Jika ada file media, tambahkan URL-nya
    if (!empty($fileUrl)) {
        // Konversi relative path ke absolute URL
        if (!preg_match('/^https?:\/\//', $fileUrl)) {
            // Get base URL dari server
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
            
            // Jika dijalankan via CLI (cron), gunakan config manual atau skip URL conversion
            if (php_sapi_name() === 'cli') {
                // Anda bisa set BASE_URL di db.php atau skip jika file sudah absolute
                $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost';
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
        CURLOPT_TIMEOUT => 0,
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

    $result = json_decode($response, true);

    // Fonnte variations observed: {status:true}, {status:'success'}, {message:'success'}, or detail.status
    $statusFlag = null;
    if (is_array($result)) {
        if (isset($result['status'])) { $statusFlag = $result['status']; }
        elseif (isset($result['message'])) { $statusFlag = $result['message']; }
        elseif (isset($result['detail']['status'])) { $statusFlag = $result['detail']['status']; }
    }
    $isSuccess = ($httpCode === 200) && (
        $statusFlag === true ||
        $statusFlag === 'true' ||
        $statusFlag === 'success' ||
        $statusFlag === 'ok'
    );

    return [
        'success' => $isSuccess,
        'response' => $result ?: $response,
        'http_code' => $httpCode,
        'error' => $err,
    ];
}

// Ambil semua log dengan status pending
$stmt = $pdo->query("
    SELECT ml.*, s.phone, s.name as subscriber_name, s.created_at as subscriber_created_at,
           m.content, m.title, m.delay_days, m.file_url
    FROM message_logs ml
    JOIN subscribers s ON ml.subscriber_id = s.id
    JOIN messages m ON ml.message_id = m.id
    WHERE ml.status = 'pending'
    ORDER BY ml.id ASC
");

$pendingLogs = $stmt->fetchAll();

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
if ($missingApiKey) {
    $missingApiKeyStmt = $pdo->prepare(
        "UPDATE message_logs SET status = 'failed', error_message = ? WHERE id = ?"
    );
}

foreach ($pendingLogs as $log) {
    $results['processed']++;
    
    // Hitung tanggal target pengiriman (tanggal daftar + delay days)
    $subscriberCreatedAt = new DateTime($log['subscriber_created_at']);
    $targetDate = clone $subscriberCreatedAt;
    $targetDate->modify('+' . $log['delay_days'] . ' days');
    $targetDate->setTime(0, 0, 0); // Set ke awal hari
    
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    // Jika belum waktunya, skip
    if ($today < $targetDate) {
        $results['skipped']++;
        $results['details'][] = [
            'subscriber' => $log['subscriber_name'],
            'phone' => $log['phone'],
            'message' => $log['title'],
            'status' => 'skipped',
            'reason' => 'Belum waktunya (target: ' . $targetDate->format('Y-m-d') . ')'
        ];
        continue;
    }

    if ($missingApiKey && $missingApiKeyStmt) {
        $missingApiKeyStmt->execute([$missingApiKeyMessage, $log['id']]);
        $results['failed']++;
        $results['details'][] = [
            'subscriber' => $log['subscriber_name'],
            'phone' => $log['phone'],
            'message' => $log['title'],
            'status' => 'failed',
            'error' => $missingApiKeyMessage
        ];
        continue;
    }
    
    // Kirim pesan
    $phone = $log['phone'];
    // Pastikan nomor dimulai dengan 62 (kode negara Indonesia untuk Fonnte)
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) !== '62') {
        $phone = '62' . $phone;
    }
    
    // Format pesan dengan personalisasi
    $messageContent = str_replace(
        ['{nama}', '{name}'],
        $log['subscriber_name'],
        $log['content']
    );
    
    // Kirim pesan dengan media jika ada
    $sendResult = sendWhatsAppMessage($phone, $messageContent, FONNTE_API_KEY, $log['file_url']);
    
    // Update log
    if ($sendResult['success']) {
        $stmt = $pdo->prepare("
            UPDATE message_logs 
            SET status = 'sent', sent_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$log['id']]);
        
        $results['sent']++;
        $results['details'][] = [
            'subscriber' => $log['subscriber_name'],
            'phone' => $log['phone'],
            'message' => $log['title'],
            'status' => 'sent'
        ];
    } else {
        $raw = $sendResult['response'];
        $msgFromApi = is_array($raw) ? ($raw['message'] ?? $raw['detail']['message'] ?? null) : null;
        $errorMsg = $msgFromApi ?: ($sendResult['error'] ?: ('HTTP ' . $sendResult['http_code']));
        // Simpan ringkas + potongan respons untuk debug
        $errorMsg = substr((string)$errorMsg, 0, 255);
        $responseSnippet = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
        $responseSnippet = $responseSnippet ? substr($responseSnippet, 0, 500) : '';
        
        $stmt = $pdo->prepare("
            UPDATE message_logs 
            SET status = 'failed', error_message = CONCAT(IFNULL(error_message,''), ' | ', ?) 
            WHERE id = ?
        ");
        $stmt->execute([$errorMsg . ($responseSnippet ? (' :: ' . $responseSnippet) : ''), $log['id']]);
        
        $results['failed']++;
        $results['details'][] = [
            'subscriber' => $log['subscriber_name'],
            'phone' => $log['phone'],
            'message' => $log['title'],
            'status' => 'failed',
            'error' => $errorMsg
        ];
    }
    
    // Delay sedikit untuk menghindari rate limit
    usleep(500000); // 0.5 detik
}

if ($missingApiKey) {
    $results['message'] = $missingApiKeyMessage;
}

// Output hasil (untuk browser atau CLI)
if (php_sapi_name() === 'cli') {
    // CLI mode (untuk cron job)
    if (!empty($results['message'])) {
        echo $results['message'] . "\n";
    }
    echo "Pengiriman selesai:\n";
    echo "- Diproses: {$results['processed']}\n";
    echo "- Terkirim: {$results['sent']}\n";
    echo "- Gagal: {$results['failed']}\n";
    echo "- Dilewati: {$results['skipped']}\n";
} else {
    // Browser mode
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>

