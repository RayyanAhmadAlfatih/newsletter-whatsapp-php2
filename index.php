<?php
declare(strict_types=1);

require_once __DIR__ . '/admin/db.php';
require_once __DIR__ . '/admin/helpers.php';

$registerToken = csrf_token('public_register');
$flashSuccess = $_SESSION['success'] ?? '';
$flashError = $_SESSION['error'] ?? '';
$flashErrors = $_SESSION['errors'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['errors']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter WhatsApp - Daftar Sekarang</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Newsletter WhatsApp</h1>
            <p>Dapatkan update terbaru langsung ke WhatsApp Anda!</p>
        </div>

        <div class="form-container">
            <form id="registerForm" action="submit.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo e($registerToken); ?>">

                <div class="form-group">
                    <label for="name">Nama Lengkap <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required placeholder="Masukkan nama Anda" maxlength="120">
                </div>

                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required placeholder="contoh@email.com" maxlength="190">
                </div>

                <div class="form-group">
                    <label for="phone">Nomor WhatsApp <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" required placeholder="08xxxxxxxxxx" pattern="[0-9]{10,13}">
                    <small>Format: 08xxxxxxxxxx (10-13 digit)</small>
                </div>

                 <div class="about-section">
            <div class="about-website">
                <h3>🌐 Tentang Website</h3>
                <p><span class="highlight">Newsletter WhatsApp</span> adalah platform otomatis canggih untuk mengirim update, berita, dan informasi penting langsung ke WhatsApp subscriber. Sistem ini dirancang dengan <span class="highlight">mudah digunakan</span> dan dapat dikustomisasi sesuai kebutuhan bisnis Anda.</p>
            </div>
            
            <div class="about-creator">
                <h3>👨‍💻 Dibuat Oleh</h3>
                <p>
Perkenalkan, <span class="highlight">Rayyan Ahmad Alfatih</span> - seorang young coder dengan <strong>big dreams</strong> dan passion coding yang luar biasa. Meski masih muda, Rayyan telah terjun serius ke dunia programming dengan dedikasi tinggi.
                <br><br>
Rayyan bukan hanya belajar coding, tapi <strong>membangun sistem, ide, dan visi</strong> masa depan. Dari pengembangan plugin WordPress, tools AI, hingga merancun roadmap teknologi, semuanya dilakukan dengan semangat <strong>"never give up"</strong> dan rasa ingin tahu yang tak ada habisnya.
                <br><br>
<strong>Visi:</strong> Membangun solusi teknologi yang bermanfaat dan menginspirasi generasi muda untuk berkontribusi di dunia digital.
                </p>
            </div>
        </div>

                <button type="submit" class="btn-submit">Daftar Sekarang</button>
            </form>

            <?php if ($flashSuccess): ?>
                <div class="message success show"><?php echo e($flashSuccess); ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="message error show"><?php echo e($flashError); ?></div>
            <?php endif; ?>
            <?php if (!empty($flashErrors)): ?>
                <div class="message error show">
                    <ul>
                        <?php foreach ($flashErrors as $err): ?>
                            <li><?php echo e($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div id="message" class="message"></div>
        </div>

        <div class="footer">
            <p>&copy; 2024 Newsletter WhatsApp. All rights reserved.</p>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>
