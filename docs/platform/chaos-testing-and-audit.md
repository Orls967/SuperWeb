# Panduan Rekayasa Kekacauan & Audit Integritas Dua Sumber (Fase 237)

## 1. Prinsip Audit Dua Sumber (Two-Source Verification)
Sistem informasi enterprise yang memproses jutaan rekonsiliasi data tidak boleh hanya bergantung pada satu sumber kebenaran tunggal (single source of truth yang tidak diverifikasi silang). Jika data database rusak, terkena silent corruption, atau dimanipulasi secara manual melewati layer aplikasi, sistem tanpa audit independen akan tetap menganggap data tersebut valid.

Prinsip **Audit Dua Sumber** mewajibkan setiap pilar utama memiliki verifikasi independen antara dua sumber yang berbeda secara fundamental:

```
+---------------------------------+        +---------------------------------+
|          Sumber A (DB)          |        |        Sumber B (Fisik)         |
|  - Tabel Status Sandbox         |   vs   |  - Filesystem Skema Fisik       |
|  - Snapshot Metrik plt_phase    |        |  - Git Baseline & Commit Tree   |
|  - Akun Ledger Saldo Tercatat   |        |  - Rantai Hash SHA-256          |
+---------------------------------+        +---------------------------------+
```

Command resmi `platform:audit` bertindak sebagai auditor independen yang menjalankan komparasi ini secara periodik.

---

## 2. Invarian yang Diverifikasi oleh `platform:audit`

Perintah `platform:audit` memverifikasi 4 dimensi integritas:

### 1. Invarian Status Mesin Sandbox (State Machine Invariant):
- Seluruh baris di tabel `plt_sandboxes` wajib memiliki status yang terdaftar di `SandboxStatus` (`requested`, `provisioning`, `seeded`, `ready`, `expiring`, `expired`, `destroyed`).
- Tidak boleh ada status liar buatan SQL mentah di luar mesin status.

### 2. Invarian Sinkronisasi Modul Konfigurasi (Registry Invariant):
- Konfigurasi `config('modules.table_prefixes.plt_')` wajib bernilai string `'Platform'`.
- Memastikan tidak ada pergeseran konfigurasi yang membongkar isolasi modular antar-pilar.

### 3. Invarian Kriptografis Hash Sertifikat (Provenance Invariant):
- Setiap metrik kualitas pada `plt_phase_metrics` wajib memiliki hash sertifikat SHA-256 yang valid.
- Verifikasi tanda tangan HMAC-SHA256 membuktikan bahwa metrik tidak pernah dimodifikasi di database secara sepihak tanpa eksekusi `quality:phase-metrics`.

### 4. Toleransi Diskrepansi Nol (Zero Discrepancy Gate):
- Perintah menghasilkan kode keluar `0` bila tidak ada selisih diskrepansi ($D = 0$).
- Jika ditemukan minimal 1 ketidaksesuaian ($D > 0$), perintah mengembalikan kode keluar `1`, membatalkan deployment CI, dan mencatat temuan ke `plt_audit_runs`.

---

## 3. Rekayasa Kekacauan (Chaos Engineering & Sabotase Uji)

Untuk membuktikan bahwa sistem audit tidak bersifat "rubber stamp" (selalu mengembalikan hijau palsu), modul `Platform` menyediakan simulator sabotase:

1. **`DatabaseTamperSimulator`**:
   - Mensimulasikan injeksi manipulasi data status langsung ke database:
     `$sandbox->update(['status' => 'corrupted_state']);`
   - Memodifikasi hash sertifikat metrik dengan hash acak palsu.
2. **`LatencyInjectionSimulator`**:
   - Mensimulasikan jitter jaringan dan lonjakan latensi acak antara 10ms hingga 250ms untuk menguji ketahanan timeout HTTP dan pendeteksian burn rate SLO.
3. **`DataLeakageTamperSimulator`**:
   - Menginjeksi payload dengan email terbuka tanpa masking (`alice.unmasked@enterprise.com`) dan NIK 16 digit asli ke dalam antrean dataset untuk membuktikan kegagalan gerbang `platform:mask-verify`.

---

## 4. Pelaporan dan Riwayat Audit (`plt_audit_runs`)

Setiap eksekusi audit dicatat secara permanen pada tabel `plt_audit_runs`:
- `run_uuid`: Pengidentifikasi audit unik per run.
- `source_a_status`: Status verifikasi sumber database.
- `source_b_status`: Status verifikasi sumber fisik/filesystem.
- `discrepancy_count`: Jumlah perbedaan yang ditemukan.
- `findings`: Array JSON berisi daftar deskripsi detail temuan anomali.
- `completed_at`: Waktu penyelesaian verifikasi.

