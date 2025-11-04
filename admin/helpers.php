<?php
/**
 * Helper functions untuk validasi, sanitasi, dan utilitas umum
 */

if (!function_exists('is_admin_logged_in')) {
    function is_admin_logged_in(): bool
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
}

if (!function_exists('require_admin_auth')) {
    function require_admin_auth(): void
    {
        if (!is_admin_logged_in()) {
            header('Location: index.php');
            exit;
        }
    }
}

if (!function_exists('logout_admin')) {
    function logout_admin(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        app_reset_session(false);

        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );

        session_destroy();
    }
}

if (!function_exists('log_security_event')) {
    function log_security_event(string $message): void
    {
        error_log('[SECURITY] ' . $message);
    }
}

if (!defined('CSRF_TOKEN_TTL')) {
    define('CSRF_TOKEN_TTL', 1800);
}

if (!function_exists('csrf_token')) {
    function csrf_token(string $key = 'default'): string
    {
        if (!isset($_SESSION['_csrf_tokens']) || !is_array($_SESSION['_csrf_tokens'])) {
            $_SESSION['_csrf_tokens'] = [];
        }

        $shouldRefresh = true;
        if (isset($_SESSION['_csrf_tokens'][$key]['value'], $_SESSION['_csrf_tokens'][$key]['generated_at'])) {
            $generatedAt = (int) $_SESSION['_csrf_tokens'][$key]['generated_at'];
            if ($generatedAt + CSRF_TOKEN_TTL > time()) {
                $shouldRefresh = false;
            }
        }

        if ($shouldRefresh) {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (Throwable $throwable) {
                $token = bin2hex(openssl_random_pseudo_bytes(32));
            }

            $_SESSION['_csrf_tokens'][$key] = [
                'value' => $token,
                'generated_at' => time(),
            ];
        }

        return $_SESSION['_csrf_tokens'][$key]['value'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token, string $key = 'default'): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        if (!isset($_SESSION['_csrf_tokens'][$key]['value'], $_SESSION['_csrf_tokens'][$key]['generated_at'])) {
            return false;
        }

        $storedToken = (string) $_SESSION['_csrf_tokens'][$key]['value'];
        $generatedAt = (int) $_SESSION['_csrf_tokens'][$key]['generated_at'];

        if ($generatedAt + CSRF_TOKEN_TTL <= time()) {
            unset($_SESSION['_csrf_tokens'][$key]);
            return false;
        }

        $isValid = hash_equals($storedToken, (string) $token);

        if ($isValid) {
            unset($_SESSION['_csrf_tokens'][$key]);
        }

        return $isValid;
    }
}

if (!function_exists('validate_csrf_or_redirect')) {
    function validate_csrf_or_redirect(?string $token, string $key, string $redirectUrl = 'index.php'): void
    {
        if (!verify_csrf_token($token, $key)) {
            $_SESSION['error'] = 'Permintaan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

/**
 * Sanitasi input untuk mencegah XSS
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validasi nomor WhatsApp Indonesia
 * Format yang diterima: 08xxx, 628xxx, +628xxx, 8xxx
 */
function validate_whatsapp_number($phone) {
    // Hapus semua karakter non-digit
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Cek panjang (minimal 10 digit, maksimal 15 digit)
    if (strlen($phone) < 10 || strlen($phone) > 15) {
        return false;
    }
    
    // Normalisasi format
    // Jika dimulai dengan 0, ganti dengan 62
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }
    // Jika dimulai dengan 8, tambahkan 62 di depan
    elseif (substr($phone, 0, 1) === '8') {
        $phone = '62' . $phone;
    }
    // Jika tidak dimulai dengan 62, return false
    elseif (substr($phone, 0, 2) !== '62') {
        return false;
    }
    
    // Cek apakah nomor valid (harus dimulai dengan 62 dan diikuti 8)
    if (!preg_match('/^628[0-9]{8,12}$/', $phone)) {
        return false;
    }
    
    return $phone;
}

/**
 * Normalisasi nomor WhatsApp ke format 62xxx
 */
function normalize_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (substr($phone, 0, 1) === '0') {
        return '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) !== '62') {
        return '62' . $phone;
    }
    
    return $phone;
}

/**
 * Validasi file upload
 * @return array ['success' => bool, 'message' => string, 'filename' => string]
 */
function validate_file_upload($file, $allowed_types = [], $max_size = 10485760) {
    // Default allowed types jika tidak dispesifikasikan
    if (empty($allowed_types)) {
        $allowed_types = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/avi', 'video/mpeg', 'video/quicktime',
            'application/pdf'
        ];
    }
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'avi', 'mpeg', 'mov', 'pdf'];
    
    // Cek error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize di php.ini)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE di form)',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP',
        ];
        
        return [
            'success' => false,
            'message' => $error_messages[$file['error']] ?? 'Error upload tidak diketahui'
        ];
    }
    
    // Cek ukuran file
    if ($file['size'] > $max_size) {
        $max_mb = $max_size / 1048576;
        return [
            'success' => false,
            'message' => "Ukuran file terlalu besar. Maksimal {$max_mb}MB"
        ];
    }
    
    // Cek MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types, true)) {
        return [
            'success' => false,
            'message' => 'Tipe file tidak diizinkan. Hanya gambar, video, dan PDF yang diperbolehkan.'
        ];
    }
    
    // Cek ekstensi file
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_extensions, true)) {
        return [
            'success' => false,
            'message' => 'Ekstensi file tidak diizinkan'
        ];
    }
    
    // Generate nama file yang aman
    $safe_filename = uniqid('', true) . '_' . time() . '.' . $file_extension;
    
    return [
        'success' => true,
        'message' => 'File valid',
        'filename' => $safe_filename,
        'original_name' => $file['name'],
        'mime_type' => $mime_type,
        'size' => $file['size']
    ];
}

/**
 * Upload file ke folder uploads
 */
function upload_file($file, $subfolder = '') {
    $base_upload_dir = dirname(__DIR__) . '/uploads/';
    
    // Buat folder uploads jika belum ada
    if (!file_exists($base_upload_dir)) {
        mkdir($base_upload_dir, 0755, true);
    }
    
    // Buat subfolder jika dispesifikasikan
    $upload_dir = $base_upload_dir;
    if (!empty($subfolder)) {
        $upload_dir .= trim($subfolder, '/') . '/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
    }
    
    // Validasi file
    $validation = validate_file_upload($file);
    if (!$validation['success']) {
        return $validation;
    }
    
    $filename = $validation['filename'];
    $destination = $upload_dir . $filename;
    
    // Pindahkan file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Return relative path dari root project
        $relative_path = 'uploads/' . ($subfolder ? trim($subfolder, '/') . '/' : '') . $filename;
        return [
            'success' => true,
            'message' => 'File berhasil diupload',
            'filename' => $filename,
            'path' => $relative_path,
            'full_path' => $destination
        ];
    }

    return [
        'success' => false,
        'message' => 'Gagal memindahkan file'
    ];
}

/**
 * Hapus file upload
 */
function delete_file($file_path) {
    if (empty($file_path)) {
        return true;
    }
    
    $full_path = dirname(__DIR__) . '/' . ltrim($file_path, '/');
    
    if (file_exists($full_path)) {
        return unlink($full_path);
    }
    
    return true;
}

/**
 * Generate pagination HTML
 */
function generate_pagination($current_page, $total_pages, $base_url) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $buildUrl = static function (string $baseUrl, int $page): string {
        if ($baseUrl === '') {
            return '?page=' . $page;
        }

        $separator = '?';
        if (strpos($baseUrl, '?') !== false) {
            $separator = substr($baseUrl, -1) === '?' ? '' : '&';
        } elseif (strpos($baseUrl, '#') !== false) {
            $separator = '&';
        }

        return $baseUrl . ($separator === '' ? '' : $separator) . 'page=' . $page;
    };
    
    $html = '<div class="pagination">';
    
    if ($current_page > 1) {
        $prev_page = $current_page - 1;
        $html .= '<a href="' . $buildUrl($base_url, $prev_page) . '" class="page-link">&laquo; Prev</a>';
    }
    
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $html .= '<a href="' . $buildUrl($base_url, 1) . '" class="page-link">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-dots">...</span>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $current_page ? 'active' : '';
        $html .= '<a href="' . $buildUrl($base_url, $i) . '" class="page-link ' . $active . '">' . $i . '</a>';
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<span class="page-dots">...</span>';
        }
        $html .= '<a href="' . $buildUrl($base_url, $total_pages) . '" class="page-link">' . $total_pages . '</a>';
    }
    
    if ($current_page < $total_pages) {
        $next_page = $current_page + 1;
        $html .= '<a href="' . $buildUrl($base_url, $next_page) . '" class="page-link">Next &raquo;</a>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Format bytes ke KB/MB/GB
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Escape output untuk mencegah XSS
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
