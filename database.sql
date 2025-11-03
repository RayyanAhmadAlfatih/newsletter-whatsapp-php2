-- Database: newsletter_wa
-- Buat database terlebih dahulu jika belum ada
CREATE DATABASE IF NOT EXISTS newsletter_wa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE newsletter_wa;

-- Tabel Subscribers
CREATE TABLE IF NOT EXISTS subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email),
    UNIQUE KEY unique_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Messages (Pesan Otomatis)
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    delay_days INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Message Logs
CREATE TABLE IF NOT EXISTS message_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    message_id INT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_subscriber_message (subscriber_id, message_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert contoh pesan otomatis
INSERT INTO messages (title, content, delay_days, is_active) VALUES
('Selamat Datang', 'Terima kasih telah bergabung dengan newsletter kami! Kami akan mengirimkan update terbaru ke WhatsApp Anda.', 0, 1),
('Pesan Follow-up 1', 'Halo! Ini adalah pesan follow-up pertama Anda. Semoga Anda menikmati konten kami.', 1, 1),
('Pesan Follow-up 2', 'Terima kasih telah setia mengikuti newsletter kami. Jangan lewatkan update terbaru!', 3, 1);

