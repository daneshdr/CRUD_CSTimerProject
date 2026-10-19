<?php
/**
 * Action: Create Solve
 * Endpoint: POST /actions/create_solve.php
 * Menyimpan data waktu solve baru ke database MySQL
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

// Tangkap payload (support JSON payload dari Fetch API atau FormData)
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$rawTimeMs = isset($payload['raw_time_ms']) ? (int)$payload['raw_time_ms'] : 0;
$scramble  = isset($payload['scramble']) ? trim($payload['scramble']) : '';
$penalty   = isset($payload['penalty']) ? trim($payload['penalty']) : 'OK';

// Validasi
if ($rawTimeMs <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Waktu solve tidak valid (harus > 0 ms).']);
    exit;
}

if (empty($scramble)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Scramble tidak boleh kosong.']);
    exit;
}

if (!in_array($penalty, ['OK', '+2', 'DNF'], true)) {
    $penalty = 'OK';
}

try {
    $stmt = $pdo->prepare("INSERT INTO `solves` (`raw_time_ms`, `scramble`, `penalty`, `created_at`) VALUES (:raw_time_ms, :scramble, :penalty, NOW())");
    $stmt->execute([
        ':raw_time_ms' => $rawTimeMs,
        ':scramble'    => $scramble,
        ':penalty'     => $penalty,
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Ambil data record yang baru di-insert
    $fetchStmt = $pdo->prepare("SELECT `id`, `raw_time_ms`, `scramble`, `penalty`, `created_at` FROM `solves` WHERE `id` = :id");
    $fetchStmt->execute([':id' => $newId]);
    $createdSolve = $fetchStmt->fetch();

    $createdSolve['display_time'] = formatSolveDisplay($createdSolve['raw_time_ms'], $createdSolve['penalty']);

    // Ambil seluruh solves terkini untuk menghitung ulang statistik
    $allSolvesStmt = $pdo->query("SELECT `id`, `raw_time_ms`, `penalty` FROM `solves` ORDER BY `id` DESC");
    $allSolves = $allSolvesStmt->fetchAll();
    $stats = calculateWcaStats($allSolves);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Solve berhasil disimpan.',
        'data'    => $createdSolve,
        'stats'   => $stats,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal menyimpan ke database: ' . $e->getMessage()]);
}
