<?php
// Pengaturan umum aplikasi

// Definisi konstanta untuk lingkungan aplikasi
define('APP_ENV', 'development'); // development, staging, production

// Pengaturan koneksi database
define('DB_HOST', 'localhost');
define('DB_SERVICE', 'ORCLPDB');
define('DB_USERNAME', 'pendaki');
define('DB_PASSWORD', 'password123');

// Pengaturan keamanan
define('SECRET_KEY', 'ganti_dengan_kunci_rahasia_anda_sendiri');
define('ENCRYPTION_ALGORITHM', 'AES-256-CBC');

// Pengaturan path
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);
define('INCLUDE_PATH', ROOT_PATH . '/includes');
define('TEMPLATE_PATH', ROOT_PATH . '/templates');

// Pengaturan logging
define('LOG_ENABLED', true);
define('LOG_PATH', ROOT_PATH . '/logs');

// Pengaturan error
define('ERROR_REPORTING', true);
if (ERROR_REPORTING) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Zona waktu default
date_default_timezone_set('Asia/Jakarta');

// Fungsi untuk memuat file konfigurasi tambahan
function loadAdditionalConfig($configFile) {
    $configPath = CONFIG_PATH . '/' . $configFile;
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        error_log("File konfigurasi tidak ditemukan: {$configFile}");
    }
}
?>