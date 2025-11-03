# Newsletter WhatsApp v2 - Sistem Drip Campaign

Sistem newsletter sederhana untuk mengirim pesan otomatis via WhatsApp menggunakan Fonnte API. Dibangun dengan PHP, MySQL, HTML, CSS, dan JavaScript.

## ✨ Fitur v2 (Latest)

1. **Form Pendaftaran** - Halaman utama dengan form pendaftaran subscriber
2. **Admin Dashboard** - Panel admin untuk mengelola sistem
3. **Manajemen Pesan** - Tambah dan kelola pesan otomatis
4. **Sistem Otomatisasi** - Pengiriman pesan otomatis berdasarkan jeda hari
5. **Log Pengiriman** - Tracking status pengiriman pesan
6. **📎 Upload Media** - Kirim gambar, video, atau PDF bersama pesan teks via Fonnte
7. **✅ Validasi Input** - Validasi nomor WhatsApp, email, dan XSS protection
8. **📄 Pagination** - Navigasi halaman untuk subscribers dan logs
9. **🔒 Security** - Upload file validation, MIME type checking, dan file size limit
10. **🎨 UI/UX Improvements** - Feedback visual yang lebih baik dan navigasi yang user-friendly

## Struktur Folder

```
newsletter-wa/
├── index.php              # Halaman utama (form pendaftaran)
├── submit.php             # Handler form pendaftaran
├── database.sql           # File SQL untuk membuat database
├── README.md              # Dokumentasi
├── admin/
│   ├── index.php          # Login admin
│   ├── dashboard.php      # Dashboard admin
│   ├── subscribers.php    # Daftar subscribers
│   ├── add_message.php    # Tambah pesan otomatis
│   ├── messages.php       # Daftar pesan
│   ├── logs.php           # Log pengiriman
│   ├── send_auto.php      # Script otomatisasi pengiriman
│   └── db.php             # Konfigurasi database
└── assets/
    ├── css/
    │   ├── style.css      # Styling halaman utama
    │   └── admin.css      # Styling admin panel
    └── js/
        └── script.js      # JavaScript untuk halaman utama
```

## Instalasi

### 1. Persyaratan

- PHP 7.4 atau lebih baru
- MySQL/MariaDB
- Web server (Apache/Nginx)
- cURL extension untuk PHP
- Akun Fonnte API (untuk pengiriman WhatsApp)

### 2. Setup Database

1. Buat database MySQL:
```sql
CREATE DATABASE newsletter_wa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import file `database.sql`:
```bash
mysql -u root -p newsletter_wa < database.sql
```

Atau melalui phpMyAdmin:
- Buka phpMyAdmin
- Pilih database `newsletter_wa`
- Klik tab "Import"
- Pilih file `database.sql`
- Klik "Go"

### 3. Konfigurasi

Edit file `admin/db.php`:

```php
// Konfigurasi Database
define('DB_HOST', 'localhost');      // Host database
define('DB_NAME', 'newsletter_wa');   // Nama database
define('DB_USER', 'root');            // Username database
define('DB_PASS', '');                // Password database

// Konfigurasi Fonnte API
define('FONNTE_API_KEY', 'YOUR_FONNTE_API_KEY'); // Ganti dengan API key Fonnte Anda
define('FONNTE_API_URL', 'https://api.fonnte.com/send');

// Konfigurasi Admin
define('ADMIN_USERNAME', 'admin');    // Username admin
define('ADMIN_PASSWORD', 'admin123'); // Password admin (GANTI dengan password yang aman!)
```

### 4. Setup Web Server

#### Apache
Pastikan mod_rewrite aktif. Copy folder project ke `htdocs` atau direktori web server Anda.

#### Nginx
Konfigurasi dasar sudah cukup untuk menjalankan aplikasi ini.

### 5. Setup Fonnte API

1. Daftar akun di [Fonnte.com](https://fonnte.com)
2. Dapatkan API Key dari dashboard Fonnte
3. Masukkan API Key ke file `admin/db.php`

## Penggunaan

### Halaman Utama

1. Buka `http://localhost/newsletter-wa/` di browser
2. Isi form pendaftaran:
   - Nama lengkap
   - Email
   - Nomor WhatsApp
3. Klik "Daftar Sekarang"

### Admin Panel

1. Buka `http://localhost/newsletter-wa/admin/`
2. Login dengan kredensial yang sudah dikonfigurasi
3. Fitur yang tersedia:
   - **Dashboard**: Lihat statistik dan subscriber terbaru
   - **Subscribers**: Lihat semua subscriber
   - **Tambah Pesan**: Buat pesan otomatis baru
   - **Daftar Pesan**: Lihat semua pesan yang sudah dibuat
   - **Log Pengiriman**: Lihat status pengiriman pesan

### Membuat Pesan Otomatis

1. Masuk ke Admin Panel
2. Klik "Tambah Pesan"
3. Isi form:
   - **Judul Pesan**: Judul pesan (untuk referensi admin)
   - **Isi Pesan**: Konten yang akan dikirim ke WhatsApp
   - **Jeda Hari**: Jumlah hari setelah pendaftaran untuk mengirim pesan (0 = hari pertama)
   - **Aktif**: Centang jika pesan aktif
4. Klik "Simpan Pesan"

**Contoh Pesan:**
- Pesan 1: Jeda 0 hari → Dikirim saat pendaftaran
- Pesan 2: Jeda 1 hari → Dikirim 1 hari setelah pendaftaran
- Pesan 3: Jeda 3 hari → Dikirim 3 hari setelah pendaftaran

**Personalization:**
Anda bisa menggunakan placeholder `{nama}` atau `{name}` dalam isi pesan, akan otomatis diganti dengan nama subscriber.

### Sistem Otomatisasi

Ada dua cara untuk menjalankan pengiriman otomatis:

#### 1. Manual (via Browser)
- Masuk ke Dashboard
- Klik "🚀 Jalankan Pengiriman"
- Sistem akan memproses dan mengirim pesan yang sudah waktunya

#### 2. Otomatis (via Cron Job)

Setup cron job untuk menjalankan script secara otomatis setiap jam:

```bash
# Edit crontab
crontab -e

# Tambahkan baris berikut (ganti path sesuai lokasi file Anda)
0 * * * * /usr/bin/php /path/to/newsletter-wa/admin/send_auto.php >> /var/log/newsletter-send.log 2>&1
```

Atau untuk Windows (Task Scheduler):
- Buat task baru
- Action: Start a program
- Program: `php.exe`
- Arguments: `D:\path\to\newsletter-wa\admin\send_auto.php`

## Database Schema

### Tabel `subscribers`
- `id` - Primary key
- `name` - Nama subscriber
- `email` - Email subscriber (unique)
- `phone` - Nomor WhatsApp (unique)
- `created_at` - Timestamp pendaftaran

### Tabel `messages`
- `id` - Primary key
- `title` - Judul pesan
- `content` - Isi pesan
- `delay_days` - Jeda hari setelah pendaftaran
- `file_url` - Path file media (gambar/video/pdf) - **NEW in v2**
- `is_active` - Status aktif/nonaktif
- `created_at` - Timestamp pembuatan

### Tabel `message_logs`
- `id` - Primary key
- `subscriber_id` - Foreign key ke subscribers
- `message_id` - Foreign key ke messages
- `status` - Status (pending/sent/failed)
- `sent_at` - Timestamp pengiriman
- `error_message` - Pesan error jika gagal
- `created_at` - Timestamp pembuatan log

## Keamanan

1. **Ganti Password Admin**: Ubah `ADMIN_PASSWORD` di `admin/db.php` dengan password yang kuat
2. **Database**: Gunakan password yang kuat untuk database
3. **File Permissions**: Set permission yang tepat untuk file konfigurasi
4. **HTTPS**: Gunakan HTTPS untuk produksi
5. **API Key**: Jangan expose API key Fonnte Anda
6. **Upload Security**: File upload sudah divalidasi MIME type, ukuran (max 10MB), dan ekstensi
7. **XSS Protection**: Semua input divalidasi dan di-escape sebelum ditampilkan
8. **SQL Injection**: Menggunakan prepared statements untuk semua query database

## Troubleshooting

### Koneksi Database Gagal
- Pastikan MySQL service berjalan
- Periksa konfigurasi di `admin/db.php`
- Pastikan database sudah dibuat

### Pesan Tidak Terkirim
- Periksa API Key Fonnte
- Pastikan nomor WhatsApp valid (format: 08xxxxxxxxxx atau 628xxxxxxxxxx)
- Cek log error di tabel `message_logs`
- Pastikan saldo Fonnte mencukupi

### Error saat Menjalankan send_auto.php
- Pastikan cURL extension aktif di PHP
- Periksa permission file
- Untuk CLI: pastikan path PHP benar

## Dukungan

Untuk pertanyaan atau bantuan, silakan buat issue di repository ini.

## Lisensi

MIT License - Bebas digunakan untuk keperluan pribadi atau komersial.

## Changelog v2

### 🆕 Fitur Baru
- Upload dan kirim media (gambar, video, PDF) via Fonnte API
- Validasi nomor WhatsApp format Indonesia (08xx, 628xx)
- Pagination untuk halaman subscribers dan logs (20 item per halaman)
- Helper functions untuk validasi, sanitasi, dan utilitas umum
- Improved error handling dan logging

### 🔒 Security Enhancements
- Validasi file upload (MIME type, size, extension)
- XSS protection dengan htmlspecialchars
- SQL Injection prevention dengan prepared statements
- File upload folder protection (.htaccess)

### 🎨 UI/UX Improvements
- Pagination styling dengan hover effects
- File input styling
- Better form feedback dengan emoji icons
- Media file preview di edit form
- Improved table layouts

### 📁 New Files
- `admin/helpers.php` - Helper functions
- `uploads/` - Folder untuk menyimpan media files
- `migration_add_file_url.sql` - Migration untuk update schema
- `CRON_SETUP.md` - Dokumentasi setup cron job
- `.gitignore` - Git ignore file

## Migrasi dari v1 ke v2

Jika Anda sudah menggunakan versi sebelumnya, jalankan migration:

```bash
mysql -u root -p newsletter_wa < migration_add_file_url.sql
```

Atau via phpMyAdmin:
```sql
ALTER TABLE messages ADD COLUMN file_url VARCHAR(500) NULL AFTER delay_days;
```

## Catatan

- Sistem ini menggunakan Fonnte API untuk pengiriman WhatsApp
- Pastikan Anda memahami terms of service Fonnte sebelum menggunakan di produksi
- Sistem ini dirancang sederhana, untuk kebutuhan yang lebih kompleks disarankan menggunakan framework modern
- Untuk mengirim media, pastikan file dapat diakses via HTTP/HTTPS (Fonnte akan download dari URL yang diberikan)

