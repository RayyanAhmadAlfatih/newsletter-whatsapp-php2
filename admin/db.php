<?php
// TEMP: enable verbose error reporting during setup (remove in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'newsletter_wa');
define('DB_USER', 'root');
define('DB_PASS', '');

// Koneksi Database menggunakan PDO
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Konfigurasi Fonnte API
define('FONNTE_API_KEY', 'YOUR_FONNTE_API_KEY'); // Ganti dengan API key Fonnte Anda
define('FONNTE_API_URL', 'https://api.fonnte.com/send');

// Konfigurasi Admin
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // Ganti dengan password yang aman

session_start();
