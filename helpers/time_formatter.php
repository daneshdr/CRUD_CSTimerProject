<?php
/**
 * Time Formatter Helper
 * Mengonversi milidetik ke representasi string standar WCA (ss.xx atau mm:ss.xx)
 */

if (!function_exists('formatTime')) {
    /**
     * Format milidetik murni ke string waktu.
     * @param int|float|null $ms
     * @return string
     */
    function formatTime($ms) {
        if ($ms === null || $ms === 'DNF' || $ms === 'Infinity' || !is_numeric($ms)) {
            return 'DNF';
        }

        $ms = (int)$ms;
        if ($ms < 0) return '0.00';

        $totalSeconds = $ms / 1000;
        $minutes = floor($totalSeconds / 60);
        $seconds = $totalSeconds - ($minutes * 60);

        if ($minutes > 0) {
            return sprintf('%d:%05.2f', $minutes, $seconds);
        } else {
            return sprintf('%.2f', $seconds);
        }
    }
}

if (!function_exists('getFinalSolveTime')) {
    /**
     * Menghitung waktu final berdasarkan penalti
     * @param int $rawTimeMs
     * @param string $penalty 'OK', '+2', 'DNF'
     * @return int|null Nilai ms akhir, atau null jika DNF
     */
    function getFinalSolveTime($rawTimeMs, $penalty) {
        if ($penalty === 'DNF') {
            return null;
        }
        if ($penalty === '+2') {
            return $rawTimeMs + 2000;
        }
        return (int)$rawTimeMs;
    }
}

if (!function_exists('formatSolveDisplay')) {
    /**
     * Memformat tampilan waktu solve dengan indikator penalti (+ atau DNF)
     * @param int $rawTimeMs
     * @param string $penalty
     * @return string
     */
    function formatSolveDisplay($rawTimeMs, $penalty) {
        if ($penalty === 'DNF') {
            return 'DNF';
        }
        if ($penalty === '+2') {
            return formatTime($rawTimeMs + 2000) . '+';
        }
        return formatTime($rawTimeMs);
    }
}
