<?php

/**
 * Copy this file to config.php and adjust the values for your environment.
 * The config.php file is excluded from version control via .gitignore.
 */

return [
    // Database configuration
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'newsletter_wa',
    'DB_USER' => 'root',
    'DB_PASS' => '',

    // Base URL used when generating absolute URLs from CLI/cron executions
    'BASE_URL' => 'http://localhost',

    // Fonnte API configuration
    'FONNTE_API_KEY' => '',
    'FONNTE_API_URL' => 'https://api.fonnte.com/send',

    // Admin login credentials
    'ADMIN_USERNAME' => 'ubah-username-admin',
    // Generate the hash using: php -r "echo password_hash('password-anda', PASSWORD_DEFAULT), PHP_EOL;"
    'ADMIN_PASSWORD_HASH' => '',
    // Optional fallback (not recommended):
    // 'ADMIN_PASSWORD' => '',
];
