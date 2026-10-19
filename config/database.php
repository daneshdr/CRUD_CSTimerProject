<?php
/**
 * Database Connection Configuration (Laragon MySQL)
 * Target Database: rubik_timer
 */

$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? '127.0.0.1'));
$port = (int)(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? 3306)));
$db   = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? 'rubik_timer'));
$user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? 'root'));
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? ($_SERVER['DB_PASS'] ?? ''));
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_SSL_CA       => true,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Tangani error koneksi dengan format JSON jika request adalah AJAX/API
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
    
    // Tampilan error human-readable jika diakses langsung
    die("Koneksi Database Gagal. Pastikan MySQL di Laragon sudah aktif! Detail: " . htmlspecialchars($e->getMessage()));
}
