# Strategi Pengujian & Verifikasi Kualitas Platform Release Engineering

Dokumen ini mendokumentasikan piramida pengujian, skenario uji penerimaan (acceptance testing), pengujian batas (boundary conditions), dan metodologi uji sabotase pada modul Platform.

---

## 1. Piramida Pengujian Modul Platform

Pengujian keandalan perangkat lunak pada modul Platform dibangun di atas empat lapisan piramida:

```
          / \
         /   \      [ Lapisan 4: Uji Sabotase & Chaos Drills ]
        /     \     - Injeksi anomali telemetri canary
       /       \    - Simulasi korupsi rantai hash audit
      /---------\
     /           \   [ Lapisan 3: Uji Penerimaan & E2E Acceptance ]
    /             \  - Siklus hidup 9 status Change Request
   /               \ - Integrasi rollback canary dan backfill chunks
  /-----------------\
 /                   \ [ Lapisan 2: Uji Fitur & Akses Rute (Feature/HTTP) ]
/                     \- Autentikasi RBAC admin/superadmin pada rute dev
/-----------------------\
/                         \ [ Lapisan 1: Uji Unit & Algoritma Murni (Unit) ]
/                           \- Algoritma A4 (Scoring), A5 (Linting), A6 (Canary)
-----------------------------
```

---

## 2. Cakupan Uji Penerimaan (Acceptance Test Suite)

Seluruh skenario pengujian utama diatur dalam direktori `tests/Acceptance/PlatformRelease/`:

### 1. `ChangeManagementAccessAndSmokeTest.php`
- **Tujuan**: Memastikan keamanan akses endpoint web dan API modul Platform.
- **Skenario**:
  - Tamu (guest) diarahkan ke halaman login saat mengakses rute `/platform/*`.
  - Pengguna dengan peran tidak berwenang (misal: `guru`, `siswa`, `staf`) menerima kode status HTTP 403 Forbidden.
  - Pengguna dengan peran `admin` atau `superadmin` dapat mengakses dashboard rilis dan manajemen perubahan dengan sukses (HTTP 200).

### 2. `ChangeRequestStateMachineAndRiskTest.php`
- **Tujuan**: Memvalidasi integritas state machine perubahan dan ketepatan algoritma penskoran risiko A4.
- **Skenario**:
  - Perubahan dengan cakupan terisolasi dan rollback teruji mendapatkan skor risiko rendah (< 25).
  - Perubahan dengan modifikasi skema DDL dan dampak multi-service mendapatkan skor risiko tinggi (> 50).
  - Transisi status ilegal (misal langsung dari `draft` ke `approved` tanpa melewati `submitted`) memicu pengecualian domain.

### 3. `DatabaseMigrationSafetyLinterTest.php`
- **Tujuan**: Memastikan linter skema A5 mampu mendeteksi pola DDL berbahaya pada berkas migrasi database.
- **Skenario**:
  - Pendeteksian berkas migrasi yang memuat `dropColumn`, `dropTable`, atau raw DDL destruktif.
  - Perintah konsol `platform:migration-lint` mengeluarkan kode status keluar 0 pada migrasi bersih dan kode status 1 saat mendeteksi pelanggaran.

### 4. `CanaryDeploymentAutoRollbackTest.php`
- **Tujuan**: Menguji ketahanan algoritma A6 dan orkestrasi pembalikan otonom.
- **Skenario**:
  - Injeksi lonjakan kesalahan canary (error rate 15% vs baseline 0.5%) memicu rekomendasi `ROLLBACK`.
  - `CanaryRollbackOrchestrator` secara instan mengubah status deployment dan change request menjadi `rolled_back`.

### 5. `ReleaseTrainBackfillAndAuditTest.php`
- **Tujuan**: Memvalidasi alur Release Train, pemrosesan backfill terfragmentasi, dan deteksi manipulasi audit.
- **Skenario**:
  - Transisi Release Train dari `open` hingga `frozen` dan penyuntikan hotfix.
  - Pemrosesan chunk data backfill yang idempoten dan dapat dilanjutkan saat terjadi kegagalan parsial.
  - Perintah `platform:change-check` berhasil memverifikasi buku besar bersih dan mendeteksi pemalsuan jejak audit.

---

## 3. Metodologi Uji Sabotase (Sabotage Verification Drill)

Sebagai bagian dari disiplin rekayasa mutu tinggi, pengujian sabotase sengaja dilakukan untuk membuktikan bahwa alat ukur tidak bersikap longgar:
1. **Sabotase State Machine**: Menonaktifkan guard transisi status secara sengaja. Pengujian harus mendeteksi kelonggaran ini dan menjadi merah (gagal).
2. **Sabotase Linter Skema**: Memasukkan berkas migrasi tiruan dengan operasi `dropColumn`. Linter wajib menangkap pelanggaran ini dan menghasilkan keluaran kode keluar 1.
3. **Sabotase Telemetri Canary**: Memanipulasi angka metrik galat. Algoritma A6 wajib menolak kenaikan lalu lintas dan memicu auto-rollback seketika.

Setelah uji sabotase terbukti memicu kegagalan pada pengujian, kode dikembalikan ke implementasi produksi yang benar hingga seluruh pengujian kembali hijau 100%.

---

## 4. Disiplin Pemeliharaan Uji Jangka Panjang

1. Setiap penambahan fitur baru pada modul Platform wajib disertai acceptance test yang menguji kasus sukses (happy path) dan kasus kegagalan (edge cases).
2. Dilarang menggunakan pemalsuan assertion tiruan (mocking berlebihan pada domain entity).
3. Pengujian arsitektur `ArchitectureTest` dijalankan pada setiap pipeline CI untuk mencegah kebocoran lapisan domain ke lapisan antarmuka pengguna (UI).
