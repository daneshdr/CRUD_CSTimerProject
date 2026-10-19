/**
 * Main Application Controller (SAGARA LAKARA Rubik Timer)
 * Mengintegrasikan Timer, WCA Scrambler, AJAX Fetch CRUD, dan UI DOM Updates
 */

document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const scrambleEl = document.getElementById('scrambleText');
    const btnNewScramble = document.getElementById('btnNewScramble');
    const timerDisplay = document.getElementById('timerDisplay');
    const timerContainer = document.getElementById('timerContainer');
    const timerInstruction = document.getElementById('timerInstruction');
    const solvesTableBody = document.getElementById('solvesTableBody');
    const emptyState = document.getElementById('emptyState');

    // Stats Elements
    const statTotal = document.getElementById('statTotal');
    const statCurrent = document.getElementById('statCurrent');
    const statBest = document.getElementById('statBest');
    const statMo3 = document.getElementById('statMo3');
    const statAo5 = document.getElementById('statAo5');
    const statAo12 = document.getElementById('statAo12');
    const statAo100 = document.getElementById('statAo100');

    let currentScramble = '';

    // ==========================================
    // 1. SCRAMBLE GENERATOR
    // ==========================================
    function refreshScramble() {
        currentScramble = Scrambler.generate(20);
        if (scrambleEl) {
            scrambleEl.textContent = currentScramble;
        }
    }

    if (btnNewScramble) {
        btnNewScramble.addEventListener('click', function () {
            refreshScramble();
            // Kembalikan fokus ke body agar spacebar langsung siap dipakai
            btnNewScramble.blur();
        });
    }

    // Inisialisasi scramble awal
    refreshScramble();

    // ==========================================
    // 2. HELPER FORMATTING
    // ==========================================
    function formatTimeMs(ms) {
        if (ms === null || ms === undefined || ms === '' || ms === '-') return '-';
        if (ms === 'DNF') return 'DNF';
        
        const num = Number(ms);
        if (isNaN(num)) return ms;

        const totalSec = num / 1000;
        const min = Math.floor(totalSec / 60);
        const sec = totalSec - (min * 60);

        if (min > 0) {
            return `${min}:${sec < 10 ? '0' : ''}${sec.toFixed(2)}`;
        }
        return sec.toFixed(2);
    }

    function formatSolveWithPenalty(rawMs, penalty) {
        if (penalty === 'DNF') return 'DNF';
        if (penalty === '+2') return formatTimeMs(rawMs + 2000) + '+';
        return formatTimeMs(rawMs);
    }

    // ==========================================
    // 3. STATS DISPLAY UPDATE
    // ==========================================
    function updateStatsDisplay(stats) {
        if (!stats) return;

        statTotal.textContent = stats.total_solves ?? '0';
        statCurrent.textContent = stats.current_single ?? '-';
        statBest.textContent = stats.best_single ?? '-';
        statMo3.textContent = stats.mo3 ?? '-';
        statAo5.textContent = stats.ao5 ?? '-';
        statAo12.textContent = stats.ao12 ?? '-';
        statAo100.textContent = stats.ao100 ?? '-';
    }

    // ==========================================
    // 4. STOPWATCH CONTROLLER
    // ==========================================
    RubikTimer.init({
        onTick: function (elapsedMs) {
            timerDisplay.textContent = formatTimeMs(elapsedMs);
        },
        onStateChange: function (state) {
            timerContainer.className = 'timer-container timer-' + state;

            if (state === 'holding') {
                timerInstruction.textContent = 'Tahan terus Spacebar...';
            } else if (state === 'ready') {
                timerInstruction.textContent = 'SIAP! Lepas Spacebar untuk mulai!';
            } else if (state === 'running') {
                timerInstruction.textContent = 'Tekan Spacebar atau tombol apapun untuk berhenti!';
                document.body.classList.add('timer-active');
            } else {
                timerInstruction.textContent = 'Tahan tombol Spacebar untuk mulai (hijau = siap)';
                document.body.classList.remove('timer-active');
            }
        },
        onSolveComplete: function (finalRawMs) {
            // Simpan solve ke database via AJAX POST
            saveSolve(finalRawMs, currentScramble);
        }
    });

    // Support klik / sentuh pada layar timer (opsional untuk kemudahan mobile/mouse)
    timerContainer.addEventListener('mousedown', function (e) {
        if (e.target.tagName.toLowerCase() === 'button') return;
        if (RubikTimer.getState() === 'running') {
            RubikTimer.stopTimer();
        }
    });

    // ==========================================
    // 5. CRUD AJAX ACTIONS
    // ==========================================

    // CREATE: Simpan solve baru ke MySQL
    async function saveSolve(rawTimeMs, scramble) {
        try {
            const response = await fetch('actions/create_solve.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    raw_time_ms: rawTimeMs,
                    scramble: scramble,
                    penalty: 'OK'
                })
            });

            const result = await response.json();

            if (result.success) {
                // Sisipkan baris baru ke tabel
                prependSolveRow(result.data);
                // Update statistik realtime
                updateStatsDisplay(result.stats);
                // Generate scramble baru untuk solve berikutnya
                refreshScramble();
            } else {
                alert('Gagal menyimpan solve: ' + (result.error || 'Terjadi kesalahan sistem.'));
            }
        } catch (err) {
            console.error('Save solve error:', err);
            alert('Koneksi ke server gagal. Pastikan Apache & MySQL Laragon menyala.');
        }
    }

    // UPDATE: Update status penalti (OK, +2, DNF)
    async function updatePenalty(id, penalty) {
        try {
            const response = await fetch('actions/update_penalty.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id: id,
                    penalty: penalty
                })
            });

            const result = await response.json();

            if (result.success) {
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    const finalTimeCell = row.querySelector('.col-final-time');
                    const badgeCell = row.querySelector('.col-penalty-badge');

                    finalTimeCell.textContent = result.data.display_time;
                    badgeCell.innerHTML = renderPenaltyBadge(result.data.penalty);

                    // Update tombol aktif pada baris
                    row.querySelectorAll('.btn-penalty').forEach(btn => {
                        if (btn.dataset.penalty === penalty) {
                            btn.classList.add('active');
                        } else {
                            btn.classList.remove('active');
                        }
                    });
                }
                updateStatsDisplay(result.stats);
            } else {
                alert('Gagal memperbarui penalti: ' + (result.error || 'Terjadi kesalahan.'));
            }
        } catch (err) {
            console.error('Update penalty error:', err);
            alert('Gagal menghubungi server.');
        }
    }

    // DELETE: Hapus solve dengan konfirmasi
    async function deleteSolve(id) {
        const confirmed = confirm('Apakah Anda yakin ingin menghapus catatan solve ini? Data yang terhapus tidak dapat dikembalikan.');
        if (!confirmed) return;

        try {
            const response = await fetch('actions/delete_solve.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ id: id })
            });

            const result = await response.json();

            if (result.success) {
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.remove();
                }
                renumberTableRows();
                updateStatsDisplay(result.stats);

                // Jika tabel kosong, tampilkan kembali empty state
                if (solvesTableBody.querySelectorAll('tr').length === 0 && emptyState) {
                    emptyState.style.display = 'table-row';
                }
            } else {
                alert('Gagal menghapus solve: ' + (result.error || 'Terjadi kesalahan.'));
            }
        } catch (err) {
            console.error('Delete solve error:', err);
            alert('Gagal menghubungi server.');
        }
    }

    // ==========================================
    // 6. DOM RENDERING HELPERS
    // ==========================================
    function renderPenaltyBadge(penalty) {
        if (penalty === '+2') {
            return `<span class="badge badge-warning">+2</span>`;
        } else if (penalty === 'DNF') {
            return `<span class="badge badge-danger">DNF</span>`;
        }
        return `<span class="badge badge-success">OK</span>`;
    }

    function prependSolveRow(solve) {
        if (emptyState) {
            emptyState.style.display = 'none';
        }

        const tr = document.createElement('tr');
        tr.setAttribute('data-id', solve.id);

        const rawFormatted = formatTimeMs(solve.raw_time_ms);
        const finalFormatted = solve.display_time || formatSolveWithPenalty(solve.raw_time_ms, solve.penalty);

        tr.innerHTML = `
            <td class="col-index">#</td>
            <td class="col-raw">${rawFormatted}</td>
            <td class="col-final-time fw-bold">${finalFormatted}</td>
            <td class="col-penalty-badge">${renderPenaltyBadge(solve.penalty)}</td>
            <td class="col-scramble text-monospace">${escapeHtml(solve.scramble)}</td>
            <td class="col-actions">
                <div class="action-buttons">
                    <button class="btn-penalty ${solve.penalty === 'OK' ? 'active' : ''}" data-penalty="OK" title="Set OK">OK</button>
                    <button class="btn-penalty ${solve.penalty === '+2' ? 'active' : ''}" data-penalty="+2" title="Set +2">+2</button>
                    <button class="btn-penalty ${solve.penalty === 'DNF' ? 'active' : ''}" data-penalty="DNF" title="Set DNF">DNF</button>
                    <button class="btn-delete" title="Hapus Solve">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </td>
        `;

        // Event delegation pada baris baru
        bindRowEvents(tr, solve.id);

        solvesTableBody.insertBefore(tr, solvesTableBody.firstChild);
        renumberTableRows();
    }

    function bindRowEvents(row, id) {
        row.querySelectorAll('.btn-penalty').forEach(btn => {
            btn.addEventListener('click', function () {
                const penalty = this.dataset.penalty;
                updatePenalty(id, penalty);
            });
        });

        const deleteBtn = row.querySelector('.btn-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                deleteSolve(id);
            });
        }
    }

    function renumberTableRows() {
        const rows = solvesTableBody.querySelectorAll('tr[data-id]');
        const total = rows.length;
        rows.forEach((r, idx) => {
            const indexCell = r.querySelector('.col-index');
            if (indexCell) {
                indexCell.textContent = total - idx;
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Hubungkan event listener untuk baris yang sudah ter-render awal oleh PHP
    document.querySelectorAll('#solvesTableBody tr[data-id]').forEach(row => {
        const id = Number(row.dataset.id);
        bindRowEvents(row, id);
    });

    renumberTableRows();
});
