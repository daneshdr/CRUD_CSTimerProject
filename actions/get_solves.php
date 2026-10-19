<?php
/**
 * Action: Get Solves
 * Endpoint: GET /actions/get_solves.php
 * Mengambil seluruh data solve dan statistik terkini dalam format JSON
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/time_formatter.php';
require_once __DIR__ . '/../helpers/stats_calculator.php';

try {
    $stmt = $pdo->query("SELECT `id`, `raw_time_ms`, `scramble`, `penalty`, `created_at` FROM `solves` ORDER BY `id` DESC");
    $solves = $stmt->fetchAll();

    foreach ($solves as &$s) {
        $s['id'] = (int)$s['id'];
        $s['raw_time_ms'] = (int)$s['raw_time_ms'];
        $s['raw_formatted'] = formatTime($s['raw_time_ms']);
        $s['display_time'] = formatSolveDisplay($s['raw_time_ms'], $s['penalty']);
    }
    unset($s);

    $stats = calculateWcaStats($solves);

    echo json_encode([
        'success' => true,
        'solves'  => $solves,
        'stats'   => $stats,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal mengambil data dari database: ' . $e->getMessage()]);
}
