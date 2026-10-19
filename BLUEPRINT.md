# TECHNICAL BLUEPRINT & ARCHITECTURE PLANNING
## Speedcubing Rubik Timer (csTimer Clone) - Native PHP & Vanilla JS

---

### 1. Database Architecture & Setup (MySQL / MariaDB on Laragon)

#### A. Setup Database via Laragon
1. **Via HeidiSQL (Rekomendasi GUI Laragon):**
   - Jalankan Laragon, klik **Start All** (Apache & MySQL).
   - Klik tombol **Database** di Laragon untuk membuka HeidiSQL (default user: `root`, password: kosong/blank, port: `3306`).
   - Klik kanan pada koneksi -> **Create new** -> **Database** -> Beri nama: `rubik_timer` -> Collation: `utf8mb4_unicode_ci`.
   - Buka tab **Query**, paste query DDL di bawah, lalu tekan tombol **Execute** (F9).

2. **Via Laragon Terminal / MySQL CLI:**
   - Di Laragon, klik tombol **Terminal**.
   - Masuk ke MySQL:
     ```bash
     mysql -u root
     ```
   - Buat database dan gunakan:
     ```sql
     CREATE DATABASE rubik_timer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     USE rubik_timer;
     ```

#### B. Skema Tabel `solves` (DDL Query)
```sql
CREATE TABLE IF NOT EXISTS `solves` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `raw_time_ms` INT UNSIGNED NOT NULL COMMENT 'Durasi murni stopwatch dalam satuan milidetik',
    `scramble` VARCHAR(255) NOT NULL COMMENT 'Algoritma scramble 20 langkah standar WCA',
    `penalty` ENUM('OK', '+2', 'DNF') NOT NULL DEFAULT 'OK' COMMENT 'Status penalti: OK, +2 (+2000ms), atau DNF',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu pencatatan solve',
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_penalty` (`penalty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### C. Penjelasan Desain Kolom & Komparasi Tipe Data
1. **`id` (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT):**
   - Identifier unik setiap solve. `INT UNSIGNED` mampu menampung hingga 4.294.967.295 record, sangat mencukupi kebutuhan ribuan solve speedcuber.
2. **`raw_time_ms` vs `DECIMAL`:**
   - **Keputusan:** Menggunakan `INT UNSIGNED` (milidetik murni).
   - **Alasan Teknis:**
     - Menghindari masalah *floating-point precision error* pada JavaScript maupun PHP.
     - Operasi sorting, filter, dan agregasi matematika (penjumlahan, pembuangan min/max) pada tipe data integer jauh lebih cepat di level CPU/Database engine.
     - Konversi ke format string display (`mm:ss.xx` atau `ss.xx`) diserahkan ke layer presentasi/helper.
3. **`penalty` (`ENUM('OK', '+2', 'DNF')` vs `VARCHAR`):**
   - **Keputusan:** Menggunakan `ENUM`.
   - **Alasan Teknis:**
     - *Data Integrity:* Membatasi input hanya pada 3 nilai valid WCA (`OK`, `+2`, `DNF`).
     - *Storage & Performance:* ENUM disimpan secara internal sebagai bilangan bulat 1 byte (sangat efisien dibandingkan VARCHAR) dan mempercepat pencarian data saat kalkulasi statistik.
4. **`scramble` (`VARCHAR(255)` NOT NULL):**
   - Notasi scramble 3x3 WCA sepanjang 20 moves umumnya memiliki panjang 55–65 karakter. `VARCHAR(255)` memberikan alokasi yang fleksibel dan hemat storage tanpa overhead `TEXT`.
5. **`created_at` (`TIMESTAMP` NOT NULL DEFAULT CURRENT_TIMESTAMP):**
   - Menyimpan timestamp waktu penyelesaian solve secara otomatis. Diberikan indeks (`idx_created_at`) untuk mempercepat query *latest solves* (Mo3, Ao5, Ao12, Ao100).

---

### 2. Arsitektur & Alur Data (Data Flow)

#### A. Integrasi Stopwatch JavaScript ke MySQL via PHP
Aplikasi Speedcubing membutuhkan responsivitas tinggi tanpa jeda reload halaman agar flow fokus pemain tidak terganggu. Oleh karena itu, pendekatan terbaik adalah **Single-Page Application (SPA) feel menggunakan Asynchronous Fetch API**:
1. **Event Spacebar & High-Precision Timer:**
   - Pengukuran waktu menggunakan `performance.now()` di JavaScript untuk presisi sub-milidetik (menghindari drift timer yang biasa terjadi pada `setInterval`).
2. **Penghentian Timer & Pengiriman Data:**
   - Saat timer berhenti, JavaScript langsung mengunci nilai durasi dalam milidetik (`raw_time_ms`) dan teks `scramble` aktif.
   - JS mengirimkan HTTP POST asynchronous via `fetch('actions/create_solve.php')` membawa JSON payload:
     ```json
     {
       "raw_time_ms": 14250,
       "scramble": "U2 R' F2 D B2 R2 D' F2 U L2 U2 B' R' D2 F' U' B2 L' F' R",
       "penalty": "OK"
     }
     ```
3. **Pemrosesan Backend (PHP & MySQL):**
   - PHP menangkap stream payload via `file_get_contents('php://input')`, memvalidasi data menggunakan Prepared Statement (PDO) untuk mencegah SQL Injection, lalu menyimpan ke database.
   - PHP merespons balik dengan status HTTP 201 dan payload data record baru (termasuk ID yang baru di-generate).
4. **Update DOM & Scramble Generator:**
   - Setelah menerima response sukses, JavaScript menyisipkan baris solve baru ke tabel riwayat (paling atas) dan langsung memperbarui panel statistik secara instan.
   - JavaScript men-generate scramble baru 20 moves untuk persiapan solve berikutnya tanpa refresh halaman.

#### B. Lokasi & Strategi Perhitungan Statistik (Mo3, Ao5, Ao12, Ao100)
- **Rekomendasi Arsitektur:** **Dual-Layer Helper Strategy (Server-side PHP + Client-side JS Helper)** dengan **PHP sebagai Single Source of Truth**.
  - **Mengapa bukan Pure SQL?**
    Aturan WCA mewajibkan pemangkasan persentase (5% terbaik & 5% terburuk untuk Ao100, 1 terbaik & 1 terburuk untuk Ao5/Ao12) serta penanganan kondisi khusus DNF (misal: 1 DNF pada Ao5 dibuang sebagai waktu terburuk, tapi >= 2 DNF menghasilkan rata-rata DNF). Logika bersyarat ini sangat kompleks dan membebani performa jika dipaksakan menggunakan query SQL bertingkat.
  - **Fungsi PHP (`helpers/stats.php`):**
    Menghitung statistik saat halaman pertama kali dimuat (`initial render`) atau saat endpoint API statistik dipanggil.
  - **Fungsi JavaScript (`assets/js/stats.js`):**
    Menghitung statistik secara optimistik di sisi client saat record baru masuk atau diedit agar UI terasa *instantaneous* (0 latency).

---

### 3. Struktur Folder & File Modular (Native PHP)

```text
SAGARA_LAKARA/
├── config/
│   └── database.php         # Koneksi PDO ke MySQL (singleton / fungsi koneksi dengan try-catch)
├── helpers/
│   ├── time_formatter.php   # Konversi milidetik ke format mm:ss.xx dan penanganan label penalti (+2, DNF)
│   └── stats_calculator.php # Logika algoritma WCA: Mo3, Ao5, Ao12, Ao100
├── actions/
│   ├── create_solve.php     # Endpoint POST: menyimpan waktu solve & scramble baru
│   ├── update_penalty.php   # Endpoint POST: mengubah status penalti (OK, +2, DNF)
│   ├── delete_solve.php     # Endpoint POST/DELETE: menghapus 1 baris solve
│   └── get_solves.php       # Endpoint GET: mengambil data riwayat solve (format JSON)
├── assets/
│   ├── css/
│   │   └── style.css        # Styling tema minimalis gelap (dark-mode csTimer style) [Fase 3]
│   └── js/
│       ├── timer.js         # State machine & logika Stopwatch (Spacebar hold/release, performance.now)
│       ├── scrambler.js     # WCA 3x3 20-move Scramble Generator (anti-redundansi sisi berurutan/berlawanan)
│       └── app.js           # Integrasi event listener, AJAX Fetch CRUD, dan update DOM tabel & statistik
├── index.php                # Layout utama: Header, Scramble Display, Timer, Stats Panel, History Table
└── BLUEPRINT.md             # Dokumen arsitektur teknis proyek
```
