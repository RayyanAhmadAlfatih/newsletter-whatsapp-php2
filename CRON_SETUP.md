# Setup Cron Job untuk Autoresponder

## Deskripsi
File `admin/send_auto.php` dapat dijalankan secara otomatis menggunakan cron job untuk mengirim pesan WhatsApp berdasarkan delay yang ditentukan.

## Cara Setup Cron Job

### 1. Via cPanel (Shared Hosting)

1. Login ke cPanel
2. Cari menu "Cron Jobs" atau "Cron Tasks"
3. Tambah cron job baru dengan setting:
   - **Minute**: Pilih interval (misalnya setiap 5 menit: */5)
   - **Hour**: * (setiap jam)
   - **Day**: * (setiap hari)
   - **Month**: * (setiap bulan)
   - **Weekday**: * (setiap hari dalam seminggu)
   - **Command**: 
   ```bash
   /usr/bin/php /home/USERNAME/public_html/admin/send_auto.php
   ```
   Ganti `USERNAME` dan path sesuai dengan path project Anda

### 2. Via Terminal (VPS/Dedicated Server)

1. Buka terminal dan edit crontab:
   ```bash
   crontab -e
   ```

2. Tambahkan baris berikut:
   ```bash
   # Kirim pesan WhatsApp setiap 5 menit
   */5 * * * * /usr/bin/php /path/to/project/admin/send_auto.php >> /path/to/project/cron.log 2>&1
   ```

3. Simpan dan keluar (Ctrl+X, lalu Y)

### 3. Verifikasi Cron Job

Untuk memverifikasi cron job sudah berjalan:
```bash
crontab -l
```

### 4. Monitor Log (Opsional)

Untuk melihat output cron job, tambahkan redirect ke file log:
```bash
*/5 * * * * /usr/bin/php /path/to/project/admin/send_auto.php >> /path/to/project/cron.log 2>&1
```

Kemudian lihat log dengan:
```bash
tail -f /path/to/project/cron.log
```

## Interval Waktu yang Disarankan

- **Setiap 5 menit**: `*/5 * * * *` (untuk respons cepat)
- **Setiap 15 menit**: `*/15 * * * *` (balanced)
- **Setiap 1 jam**: `0 * * * *` (hemat resource)
- **Setiap hari jam 9 pagi**: `0 9 * * *`

## Catatan Penting

1. **Path PHP**: Pastikan path ke PHP binary sudah benar. Bisa cek dengan:
   ```bash
   which php
   ```

2. **Permissions**: Pastikan file `send_auto.php` memiliki permission yang tepat:
   ```bash
   chmod 644 /path/to/project/admin/send_auto.php
   ```

3. **BASE_URL untuk CLI**: Jika mengirim media (file), pastikan BASE_URL sudah di-set di `db.php`:
   ```php
   define('BASE_URL', 'https://yourdomain.com');
   ```

4. **Testing**: Sebelum setup cron, test manual terlebih dahulu:
   ```bash
   php /path/to/project/admin/send_auto.php
   ```

## Troubleshooting

### Cron tidak jalan
- Cek path PHP binary: `which php` atau `which php7.4`
- Cek permission file
- Cek cron log: `grep CRON /var/log/syslog`

### Error "Permission Denied"
```bash
chmod 755 /path/to/project/admin/send_auto.php
```

### Error Database Connection
Pastikan path ke `db.php` sudah benar dan kredensial database valid.

## Alternative: Manual Trigger

Jika tidak bisa setup cron job, Anda bisa trigger manual dengan mengakses:
```
https://yourdomain.com/admin/send_auto.php
```

Catatan: Harus login sebagai admin terlebih dahulu.
