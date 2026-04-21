<?php


define('DB_HOST',     'localhost');
define('DB_USER',     'root');       // username MySQL 
define('DB_PASS',     '');           // password MySQL (XAMPP default: kosong)
define('DB_NAME',     'dimsum_app');
define('DB_CHARSET',  'utf8mb4');


function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:20px;background:#fff3f3;border:1px solid red;margin:20px;border-radius:8px;">
                <h3 style="color:red">❌ Koneksi Database Gagal</h3>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p><strong>Solusi:</strong></p>
                <ul>
                    <li>Pastikan MySQL/MariaDB sudah berjalan di XAMPP</li>
                    <li>Cek username & password di file <code>includes/db.php</code></li>
                    <li>Pastikan database <code>dimsum_app</code> sudah dibuat (import <code>database/dimsum.sql</code>)</li>
                </ul>
            </div>');
        }
    }
    return $pdo;
}
