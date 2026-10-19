<?php
/**
 * Action: Update Penalty
 * Endpoint: POST /actions/update_penalty.php
 * Mengubah status penalti solve ('OK', '+2', 'DNF')
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

$id      = isset($payload['id']) ? (int)$payload['id'] : 0;
$penalty = isset($payload['penalty']) ? trim($payload['penalty']) : '';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID solve tidak valid.']);
    exit;
}

if (!in_array($penalty, ['OK', '+2', 'DNF'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Status penalti harus OK, +2, atau DNF.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE `solves` SET `penalty` = :penalty WHERE `id` = :id");
    $stmt->execute([
        ':penalty' => $penalty,
        ':id'      => $id,
    ]);

    // Ambil data terbaru dari solve yang diupdate
    $fetchStmt = $pdo->prepare("SELECT `id`, `raw_time_ms`, `scramble`, `penalty`, `created_at` FROM `solves` WHERE `id` = :id");
    $fetchStmt->execute([':id' => $id]);
    $updatedSolve = $fetchStmt->fetch();

    if (!$updatedSolve) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Data solve tidak ditemukan.']);
        exit;
    }

    $updatedSolve['id'] = (int)$updatedSolve['id'];
    $updatedSolve['raw_time_ms'] = (int)$updatedSolve['raw_time_ms'];
    $updatedSolve['display_time'] = formatSolveDisplay($updatedSolve['raw_time_ms'], $updatedSolve['penalty']);

    // Hitung ulang statistik
    $allSolvesStmt = $pdo->query("SELECT `id`, `raw_time_ms`, `penalty` FROM `solves` ORDER BY `id` DESC");
    $allSolves = $allSolvesStmt->fetchAll();
    $stats = calculateWcaStats($allSolves);

    echo json_encode([
        'success' => true,
        'message' => 'Status penalti berhasil diperbarui.',
        'data'    => $updatedSolve,
        'stats'   => $stats,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal memperbarui status penalti: ' . $e->getMessage()]);
}
