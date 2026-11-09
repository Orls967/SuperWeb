# Protokol Karantina & Tata Kelola Hotfix Out-of-Band

Dokumen ini menjelaskan protokol teknis dan manajerial dalam menangani perbaikan darurat (hotfix) di luar jadwal rilis reguler pada modul Platform.

---

## 1. Definisi dan Batasan Ruang Lingkup Hotfix

Hotfix adalah perubahan kode yang diterapkan secara mendesak untuk mengatasi cacat kritis (critical defects) pada lingkungan produksi:
- Hotfix **hanya** diizinkan untuk memperbaiki bug atau kerentanan keamanan yang terbukti menurunkan ketersediaan layanan produksi.
- Penambahan fungsionalitas fitur baru, pembersihan kode (refactoring non-kritis), atau pembaruan dependensi minor dilarang keras dimasukkan ke dalam paket hotfix.
- Setiap hotfix harus ditautkan ke tiket insiden yang sedang aktif.

---

## 2. Tingkat Keparahan Hotfix & Matriks Kewenangan

Pengkategorian keparahan diatur oleh enum `HotfixSeverity`:

| Tingkat | Kriteria Dampak | Persetujuan Minimum | Prosedur Pengujian |
|---|---|---|---|
| `patch` | Regresi minor non-transaksional; tidak ada kehilangan data | Tech Lead Modul | Pengujian unit terisolasi dan pengujian integrasi lokal |
| `urgent` | Gangguan pada alur transaksi atau kinerja sistem yang memburuk cepat | Lead Architect & QA Lead | Uji regresi otomatis staging dan smoke test canary |
| `critical` | Kerentanan keamanan tingkat tinggi (RCE, SQLi, Auth Bypass) atau korupsi data | VP Engineering & CAB | Karantina ketat, audit kode manual ganda, dan canary 1% |

---

## 3. Protokol Karantina (Quarantine Protocol)

Sebelum hotfix diizinkan digabungkan ke cabang rilis atau diterapkan ke produksi, komponen `HotfixImpactQuarantineManager` menjalankan evaluasi isolasi:

```
[ Pembuatan Patch Hotfix ]
            │
            ▼
[ Evaluasi Karantina: HotfixImpactQuarantineManager ]
            │
            ├─► 1. Cek Dampak Perubahan Skema: Dilarang mengubah skema DDL mayor
            ├─► 2. Cek Ukuran Diff: Maksimum 200 baris kode logika
            ├─► 3. Cek Modul Silang: Hanya boleh menyentuh berkas modul terdampak
            ▼
 ┌──────────────────────┐
 │ Lulus Uji Karantina? │
 └──────────┬───────────┘
     │            │
  (Ya)          (Tidak)
     │            │
     ▼            ▼
[ Rilis Patch ] [ Tolak & Revisi ]
```

### Kriteria Kelulusan Karantina:
1. **Batas Ukuran Patch**: Total baris kode yang diubah tidak melebihi batas 200 LOC untuk meminimalkan risiko regresi sampingan.
2. **Tidak Ada DDL Destruktif**: Patch tidak boleh memuat DDL yang memerlukan table copy atau lock eksklusif.
3. **Penyertaan Uji Regresi**: Setiap hotfix wajib menyertakan minimal 1 unit test atau acceptance test baru yang mereproduksi bug dan membuktikan perbaikan telah bekerja.

---

## 4. Penomoran Versi Semantik (Semantic Versioning)

Penerapan hotfix menaikkan nomor versi rilis secara otomatis melalui `ReleaseTrainVersionManager`:
- Format versi standar: `vMAJOR.MINOR.PATCH` (contoh: `v1.4.0` -> `v1.4.1`).
- Jika hotfix diterapkan pada train yang sedang dalam status `freezing`, nomor build internal dinaikkan tanpa mengubah label versi rilis utama.

---

## 5. Sinkronisasi Mundur ke Cabang Utama (Backporting SOP)

Setelah hotfix berhasil diterapkan dan distabilkan di lingkungan produksi:
1. Patch hotfix wajib segera di-cherry-pick atau digabungkan kembali ke cabang `master` dan kereta rilis berikutnya (`next release train`).
2. Hal ini bertujuan untuk mencegah insiden berulang (regression reappearance) pada siklus rilis terjadwal mendatang.
3. Audit ledger mencatat penutupan tiket hotfix dan memverifikasi integritas merge commit.

