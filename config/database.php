<?php
/**
 * Database Connection Configuration (Laragon MySQL)
 * Target Database: rubik_timer
 */

$host = '127.0.0.1';
$port = 3306;
$db   = 'rubik_timer';
$user = 'root';
$pass = ''; // Default Laragon root password kosong
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
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
