<?php
/**
 * Action: Delete Solve
 * Endpoint: POST /actions/delete_solve.php
 * Menghapus 1 baris solve berdasarkan ID
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/time_formatter.php';
require_once __DIR__ . '/../helpers/stats_calculator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Gunakan metode POST.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$id = isset($payload['id']) ? (int)$payload['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID solve tidak valid.']);
    exit;
}

try {
    // Cek apakah data solve ada
    $checkStmt = $pdo->prepare("SELECT `id` FROM `solves` WHERE `id` = :id");
    $checkStmt->execute([':id' => $id]);
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Data solve tidak ditemukan atau sudah dihapus.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM `solves` WHERE `id` = :id");
    $stmt->execute([':id' => $id]);

    // Hitung ulang statistik setelah penghapusan
    $allSolvesStmt = $pdo->query("SELECT `id`, `raw_time_ms`, `penalty` FROM `solves` ORDER BY `id` DESC");
    $allSolves = $allSolvesStmt->fetchAll();
    $stats = calculateWcaStats($allSolves);

    echo json_encode([
        'success'    => true,
        'message'    => 'Data solve berhasil dihapus.',
        'deleted_id' => $id,
        'stats'      => $stats,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal menghapus data dari database: ' . $e->getMessage()]);
}
