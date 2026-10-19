<?php
/**
 * SAGARA LAKARA - Speedcubing Rubik Timer (csTimer Clone)
 * Halaman Utama (Main View)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/time_formatter.php';
require_once __DIR__ . '/helpers/stats_calculator.php';

// Ambil seluruh riwayat solves saat halaman pertama kali dibuka
try {
    $stmt = $pdo->query("SELECT `id`, `raw_time_ms`, `scramble`, `penalty`, `created_at` FROM `solves` ORDER BY `id` DESC");
    $initialSolves = $stmt->fetchAll();
} catch (PDOException $e) {
    $initialSolves = [];
}

// Kalkulasi statistik awal server-side
$initialStats = calculateWcaStats($initialSolves);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAGARA LAKARA | Speedcubing Timer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Header & Navigation -->
    <header class="app-header">
        <div class="logo-area">
            <div class="logo-icon">⏱️</div>
            <span class="app-title">Rubik Timer</span>
            <span class="app-badge">WCA 3x3</span>
        </div>
        <div class="header-meta">
            Laragon Edition &bull; Native PHP &amp; Vanilla JS
        </div>
    </header>

    <!-- Top Scramble Bar -->
    <section class="scramble-section" aria-label="WCA Scramble">
        <div class="scramble-text" id="scrambleText">Memuat Scramble...</div>
        <button class="btn-icon" id="btnNewScramble" title="Generate Scramble Baru">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            <span>New</span>
        </button>
    </section>

    <!-- Main Stopwatch Timer Display -->
    <main class="timer-section" id="timerArea">
        <div class="timer-container" id="timerContainer">
            <div class="timer-digits" id="timerDisplay">0.00</div>
            <div class="timer-instruction" id="timerInstruction">
                Tahan tombol <strong>Spacebar</strong> untuk mulai (merah: tahan, hijau: siap lepas)
            </div>
        </div>
    </main>

    <!-- WCA Realtime Statistics Grid -->
    <section class="stats-section" aria-label="Statistik WCA">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Solves</div>
                <div class="stat-value" id="statTotal"><?= htmlspecialchars((string)($initialStats['total_solves'] ?? '0')) ?></div>
            </div>
            <div class="stat-card highlight">
                <div class="stat-label">Current</div>
                <div class="stat-value" id="statCurrent"><?= htmlspecialchars((string)($initialStats['current_single'] ?? '-')) ?></div>
            </div>
            <div class="stat-card best">
                <div class="stat-label">Best</div>
                <div class="stat-value" id="statBest"><?= htmlspecialchars((string)($initialStats['best_single'] ?? '-')) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Mo3</div>
                <div class="stat-value" id="statMo3"><?= htmlspecialchars((string)($initialStats['mo3'] ?? '-')) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Ao5</div>
                <div class="stat-value" id="statAo5"><?= htmlspecialchars((string)($initialStats['ao5'] ?? '-')) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Ao12</div>
                <div class="stat-value" id="statAo12"><?= htmlspecialchars((string)($initialStats['ao12'] ?? '-')) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Ao100</div>
                <div class="stat-value" id="statAo100"><?= htmlspecialchars((string)($initialStats['ao100'] ?? '-')) ?></div>
            </div>
        </div>
    </section>

    <!-- History Solves Table (Full CRUD) -->
    <section class="history-section" aria-label="Riwayat Solve">
        <div class="history-header">
            <div class="history-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                </svg>
                Riwayat Solve
            </div>
        </div>

        <div class="table-responsive">
            <table class="solves-table" id="solvesTable">
                <thead>
                    <tr>
                        <th class="col-index">No</th>
                        <th class="col-raw">Asli</th>
                        <th class="col-final-time">Akhir</th>
                        <th class="col-penalty-badge">Status</th>
                        <th class="col-scramble">Scramble</th>
                        <th class="col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody id="solvesTableBody">
                    <?php if (empty($initialSolves)): ?>
                        <tr id="emptyState">
                            <td colspan="6" class="empty-state-cell">
                                Belum ada catatan solve. Tekan dan tahan <strong>Spacebar</strong> untuk memulai solve pertamamu!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $totalRows = count($initialSolves);
                        foreach ($initialSolves as $idx => $solve): 
                            $solveNo = $totalRows - $idx;
                            $rawFormatted = formatTime($solve['raw_time_ms']);
                            $finalFormatted = formatSolveDisplay($solve['raw_time_ms'], $solve['penalty']);
                        ?>
                            <tr data-id="<?= htmlspecialchars((string)$solve['id']) ?>">
                                <td class="col-index"><?= $solveNo ?></td>
                                <td class="col-raw"><?= htmlspecialchars($rawFormatted) ?></td>
                                <td class="col-final-time fw-bold"><?= htmlspecialchars($finalFormatted) ?></td>
                                <td class="col-penalty-badge">
                                    <?php if ($solve['penalty'] === '+2'): ?>
                                        <span class="badge badge-warning">+2</span>
                                    <?php elseif ($solve['penalty'] === 'DNF'): ?>
                                        <span class="badge badge-danger">DNF</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">OK</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-scramble text-monospace" title="<?= htmlspecialchars($solve['scramble']) ?>">
                                    <?= htmlspecialchars($solve['scramble']) ?>
                                </td>
                                <td class="col-actions">
                                    <div class="action-buttons">
                                        <button class="btn-penalty <?= $solve['penalty'] === 'OK' ? 'active' : '' ?>" data-penalty="OK" title="Set OK">OK</button>
                                        <button class="btn-penalty <?= $solve['penalty'] === '+2' ? 'active' : '' ?>" data-penalty="+2" title="Set +2">+2</button>
                                        <button class="btn-penalty <?= $solve['penalty'] === 'DNF' ? 'active' : '' ?>" data-penalty="DNF" title="Set DNF">DNF</button>
                                        <button class="btn-delete" title="Hapus Solve">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Footer -->
    <footer class="app-footer">
        Rubik Timer &bull; Speedcubing Practice &bull; Sesuai Regulasi Notasi WCA
    </footer>

    <!-- Scripts -->
    <script src="assets/js/scrambler.js"></script>
    <script src="assets/js/timer.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
