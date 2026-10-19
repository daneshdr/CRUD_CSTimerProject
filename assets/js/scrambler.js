/**
 * WCA 3x3 Scramble Generator
 * Menghasilkan scramble 20-langkah sesuai standar World Cube Association.
 * 
 * Aturan Validasi:
 * 1. Tidak boleh ada langkah berurutan pada sisi yang sama (misal: R R', U U2).
 * 2. Tidak boleh ada langkah beruntun pada sumbu yang sama jika bersilang (misal: R L R', U D U2).
 */

const Scrambler = (function () {
    const FACES = ['R', 'L', 'U', 'D', 'F', 'B'];
    const MODIFIERS = ['', "'", '2'];

    // Pemetaan sumbu (axis): 0 = R/L (X), 1 = U/D (Y), 2 = F/B (Z)
    const AXES = {
        'R': 0, 'L': 0,
        'U': 1, 'D': 1,
        'F': 2, 'B': 2
    };

    /**
     * Menghasilkan string scramble 3x3 sepanjang N langkah (default: 20 langkah WCA)
     * @param {number} length 
     * @returns {string}
     */
    function generate(length = 20) {
        const moves = [];
        let lastFace = null;
        let secondLastFace = null;

        for (let i = 0; i < length; i++) {
            let possibleFaces = FACES.filter(face => {
                // Aturan 1: Sisi tidak boleh sama persis dengan sisi sebelumnya
                if (face === lastFace) {
                    return false;
                }

                // Aturan 2: Jika 2 langkah sebelumnya berada pada sumbu yang sama (misal R dan L),
                // langkah saat ini tidak boleh berada di sumbu itu lagi.
                if (secondLastFace !== null && lastFace !== null) {
                    const currentAxis = AXES[face];
                    const lastAxis = AXES[lastFace];
                    const secondLastAxis = AXES[secondLastFace];

                    if (currentAxis === lastAxis && lastAxis === secondLastAxis) {
                        return false;
                    }
                }

                return true;
            });

            // Pilih sisi secara acak dari pilihan yang valid
            const chosenFace = possibleFaces[Math.floor(Math.random() * possibleFaces.length)];
            const chosenModifier = MODIFIERS[Math.floor(Math.random() * MODIFIERS.length)];

            moves.push(chosenFace + chosenModifier);

            secondLastFace = lastFace;
            lastFace = chosenFace;
        }

        return moves.join(' ');
    }

    return {
        generate: generate
    };
})();
