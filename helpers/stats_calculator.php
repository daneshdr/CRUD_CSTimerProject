<?php
/**
 * WCA Speedcubing Statistics Calculator Helper
 * Menghitung Mo3, Ao5, Ao12, Ao100, Best Single, dan Current Single
 */

require_once __DIR__ . '/time_formatter.php';

if (!function_exists('calculateWcaStats')) {
    /**
     * Menghitung statistik lengkap berdasarkan daftar solve yang terurut dari terbaru ke terlama (DESC)
     * @param array $solves Array asosiatif dari tabel solves: [['raw_time_ms' => ..., 'penalty' => ...], ...]
     * @return array
     */
    function calculateWcaStats(array $solves) {
        $totalSolves = count($solves);

        $stats = [
            'total_solves' => $totalSolves,
            'current_single' => null,
            'best_single'    => null,
            'mo3'            => null,
            'ao5'            => null,
            'ao12'           => null,
            'ao100'          => null,
        ];

        if ($totalSolves === 0) {
            return $stats;
        }

        // Current Single (Solve paling baru / index 0)
        $latest = $solves[0];
        $stats['current_single'] = formatSolveDisplay($latest['raw_time_ms'], $latest['penalty']);

        // Best Single (Mencari waktu terbaik dari seluruh solve yang bukan DNF)
        $bestMs = null;
        foreach ($solves as $s) {
            if ($s['penalty'] === 'DNF') continue;
            $effTime = ($s['penalty'] === '+2') ? $s['raw_time_ms'] + 2000 : $s['raw_time_ms'];
            if ($bestMs === null || $effTime < $bestMs) {
                $bestMs = $effTime;
            }
        }
        $stats['best_single'] = ($bestMs !== null) ? formatTime($bestMs) : 'DNF';

        // 1. Mean of 3 (Mo3) - 3 solve terakhir
        if ($totalSolves >= 3) {
            $slice3 = array_slice($solves, 0, 3);
            $stats['mo3'] = calculateMo3($slice3);
        }

        // 2. Average of 5 (Ao5) - 5 solve terakhir
        if ($totalSolves >= 5) {
            $slice5 = array_slice($solves, 0, 5);
            $stats['ao5'] = calculateTrimmedAverage($slice5, 1);
        }

        // 3. Average of 12 (Ao12) - 12 solve terakhir
        if ($totalSolves >= 12) {
            $slice12 = array_slice($solves, 0, 12);
            $stats['ao12'] = calculateTrimmedAverage($slice12, 1);
        }

        // 4. Average of 100 (Ao100) - 100 solve terakhir
        if ($totalSolves >= 100) {
            $slice100 = array_slice($solves, 0, 100);
            $stats['ao100'] = calculateTrimmedAverage($slice100, 5); // buang 5% = 5 solve
        }

        return $stats;
    }
}

if (!function_exists('calculateMo3')) {
    /**
     * Hitung Mean of 3 murni
     * @param array $threeSolves Array 3 record solve
     * @return string
     */
    function calculateMo3(array $threeSolves) {
        $sum = 0;
        foreach ($threeSolves as $s) {
            if ($s['penalty'] === 'DNF') {
                return 'DNF';
            }
            $time = ($s['penalty'] === '+2') ? $s['raw_time_ms'] + 2000 : $s['raw_time_ms'];
            $sum += $time;
        }
        $avg = round($sum / 3);
        return formatTime($avg);
    }
}

if (!function_exists('calculateTrimmedAverage')) {
    /**
     * Hitung Trimmed Average standar WCA (Ao5, Ao12, Ao100)
     * @param array $solvesSlice Array record solve sejumlah N
     * @param int $trimCount Jumlah solve terbaik & terburuk yang dibuang (1 untuk Ao5/Ao12, 5 untuk Ao100)
     * @return string
     */
    function calculateTrimmedAverage(array $solvesSlice, $trimCount) {
        $count = count($solvesSlice);
        $times = [];
        $dnfCount = 0;

        foreach ($solvesSlice as $s) {
            if ($s['penalty'] === 'DNF') {
                $dnfCount++;
                $times[] = INF; // DNF diposisikan sebagai waktu tak terhingga
            } else {
                $eff = ($s['penalty'] === '+2') ? $s['raw_time_ms'] + 2000 : (int)$s['raw_time_ms'];
                $times[] = $eff;
            }
        }

        // Jika jumlah DNF melebihi kuota waktu terlambat yang boleh dibuang -> DNF
        if ($dnfCount > $trimCount) {
            return 'DNF';
        }

        // Urutkan nilai dari terkecil ke terbesar
        sort($times, SORT_NUMERIC);

        // Buang $trimCount dari depan (tercepat) dan $trimCount dari belakang (terburuk / DNF)
        $trimmed = array_slice($times, $trimCount, $count - (2 * $trimCount));

        if (empty($trimmed)) {
            return 'DNF';
        }

        $sum = array_sum($trimmed);
        $avg = round($sum / count($trimmed));

        return formatTime($avg);
    }
}
