/**
 * High-Precision Stopwatch Module (csTimer Style)
 * State machine: IDLE -> HOLDING (red) -> READY (green) -> RUNNING -> STOPPED
 */

const RubikTimer = (function () {
    const STATES = {
        IDLE: 'idle',
        HOLDING: 'holding',
        READY: 'ready',
        RUNNING: 'running',
        STOPPED: 'stopped'
    };

    let state = STATES.IDLE;
    let startTime = 0;
    let elapsedMs = 0;
    let animFrameId = null;
    let holdTimeoutId = null;
    const HOLD_TIME_MS = 300; // Durasi tahan spacebar hingga warna hijau (ready)

    // Callbacks
    let onTickCallback = null;
    let onStateChangeCallback = null;
    let onSolveCompleteCallback = null;

    function init(callbacks = {}) {
        onTickCallback = callbacks.onTick || null;
        onStateChangeCallback = callbacks.onStateChange || null;
        onSolveCompleteCallback = callbacks.onSolveComplete || null;

        attachEvents();
    }

    function changeState(newState) {
        state = newState;
        if (onStateChangeCallback) {
            onStateChangeCallback(state);
        }
    }

    function update() {
        if (state !== STATES.RUNNING) return;
        elapsedMs = performance.now() - startTime;
        if (onTickCallback) {
            onTickCallback(elapsedMs);
        }
        animFrameId = requestAnimationFrame(update);
    }

    function startTimer() {
        startTime = performance.now();
        elapsedMs = 0;
        changeState(STATES.RUNNING);
        animFrameId = requestAnimationFrame(update);
    }

    function stopTimer() {
        if (animFrameId) {
            cancelAnimationFrame(animFrameId);
            animFrameId = null;
        }
        elapsedMs = Math.round(performance.now() - startTime);
        changeState(STATES.STOPPED);

        if (onTickCallback) {
            onTickCallback(elapsedMs);
        }

        if (onSolveCompleteCallback) {
            onSolveCompleteCallback(elapsedMs);
        }

        // Kembali ke IDLE setelah jeda singkat
        setTimeout(() => {
            if (state === STATES.STOPPED) {
                changeState(STATES.IDLE);
            }
        }, 400);
    }

    function handleKeyDown(e) {
        // Abaikan jika fokus sedang berada pada input, select, textarea, atau tombol aksi
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        if (['input', 'textarea', 'select'].includes(activeTag)) {
            return;
        }

        // Jika timer sedang jalan, tombol apa saja yang ditekan akan MENGHENTIKAN timer
        if (state === STATES.RUNNING) {
            e.preventDefault();
            stopTimer();
            return;
        }

        // Hanya tombol SPACEBAR yang digunakan untuk memulai timer
        if (e.code === 'Space' || e.key === ' ') {
            e.preventDefault();

            if (e.repeat) return; // Abaikan autofire keydown bawaan OS

            if (state === STATES.IDLE) {
                changeState(STATES.HOLDING);

                // Tunggu HOLD_TIME_MS agar berubah menjadi hijau (READY)
                holdTimeoutId = setTimeout(() => {
                    if (state === STATES.HOLDING) {
                        changeState(STATES.READY);
                    }
                }, HOLD_TIME_MS);
            }
        }
    }

    function handleKeyUp(e) {
        if (e.code === 'Space' || e.key === ' ') {
            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            if (['input', 'textarea', 'select'].includes(activeTag)) {
                return;
            }

            e.preventDefault();

            if (holdTimeoutId) {
                clearTimeout(holdTimeoutId);
                holdTimeoutId = null;
            }

            if (state === STATES.READY) {
                // Spacebar dilepas saat READY -> Mulai timer
                startTimer();
            } else if (state === STATES.HOLDING) {
                // Dilepas terlalu cepat sebelum hijau -> Batal start
                changeState(STATES.IDLE);
            }
        }
    }

    function attachEvents() {
        window.addEventListener('keydown', handleKeyDown);
        window.addEventListener('keyup', handleKeyUp);
    }

    function getState() {
        return state;
    }

    return {
        init: init,
        getState: getState,
        stopTimer: stopTimer,
        startTimer: startTimer,
        changeState: changeState
    };
})();
