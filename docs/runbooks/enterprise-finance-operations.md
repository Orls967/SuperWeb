# Runbook Operasional: Command Terjadwal & Pemulihan Insiden EnterpriseFinance (Fase 54)

## 1. Katalog Command Terjadwal & Rutinitas Sistem

Platform Enterprise Finance mengandalkan 8 perintah CLI otomasi yang terintegrasi langsung dengan penjadwal Laravel (`app/Console/Kernel.php` / Artisan scheduler):

| Nama Command | Frekuensi | Parameter | Idempotensi | Dampak Bisnis |
| :--- | :--- | :--- | :--- | :--- |
| `enterprise:budget-sync` | Tiap Jam (`0 * * * *`) | `--dry-run` | Ya (kunci hash komitmen) | Menyinkronkan komitmen PO Procurement ke `ef_budget_encumbrances`. |
| `enterprise:close-checklist` | Akhir Bulan / Manual | `{period?} {--entity=}` | Ya (kunci entity+period) | Menguji prasyarat rekonsiliasi sebelum penutupan buku permanen. |
| `enterprise:compliance-escalate` | Harian 07:00 (`0 7 * * *`) | `--as-of=` | Ya (kunci deadline+tier) | Mengirim notifikasi eskalasi H-30 s/d Overdue ke penanggung jawab. |
| `enterprise:control-tests` | Harian 01:00 (`0 1 * * *`) | `--sample-size=50` | Ya (kunci tanggal uji) | Menjalankan uji otomatis RCM ICFR dan menerbitkan CoreTask saat defisiensi. |
| `enterprise:data-pack` | Sesuai Kebutuhan | `{period?} {--entity=} {--out=}` | Ya (output deterministik) | Menghasilkan bundel ZIP bukti audit eksternal + manifest SHA-256. |
| `enterprise:sod-scan` | Harian 02:00 (`0 2 * * *`) | `--strict` | Ya (kunci scan harian) | Memindai konflik SoD aktif dan mencatat log anomali. |
| `enterprise:tax-recon` | Bulanan (`0 3 1 * *`) | `{period?} {--entity=}` | Ya (kunci entity+period) | Merekonsiliasi PPN masukan vs keluaran dan nomor faktur gapless. |
| `enterprise:audit` | Harian 04:00 (`0 4 * * *`) | `{--entity=} {--period=}` | Ya (read-only audit) | Audit dua sumber 10 dimensi (buku besar vs subledger & fakta). |

---

## 2. Tata Cara Konfigurasi Penjadwal Otomatis (Crontab Host)

Untuk memastikan rutinitas berjalan tanpa interupsi, pastikan entri crontab host telah terdaftar:
```bash
* * * * * cd /path/to/platform && php artisan schedule:run >> /dev/null 2>&1
```

Rincian penjadwalan khusus Enterprise Finance:
```php
$schedule->command('enterprise:budget-sync')->hourly()->withoutOverlapping();
$schedule->command('enterprise:compliance-escalate')->dailyAt('07:00');
$schedule->command('enterprise:control-tests --sample-size=50')->dailyAt('01:00');
$schedule->command('enterprise:sod-scan --strict')->dailyAt('02:00');
$schedule->command('enterprise:audit')->dailyAt('04:00');
```

---

## 3. Matriks Eskalasi Tenggat Kepatuhan (Compliance Calendar Tiers)

Command `enterprise:compliance-escalate` memindai seluruh kewajiban regulasi dan pajak dari tiga sumber adaptif:
1. `TaxComplianceDeadlineSource`: SPT Masa PPN (setiap akhir bulan berikutnya), SPT Tahunan Badan (30 April).
2. `ContractComplianceDeadlineSource`: Kedaluwarsa perjanjian sewa tenant Mall, kontrak armada logistik, polis asuransi aset.
3. `RegulatoryLicenseComplianceDeadlineSource`: Perpanjangan izin edar Halal/BPOM F&B, sertifikasi laik jalan armada logistik, izin lingkungan AMDAL.

Tingkat eskalasi diterbitkan sesuai ambang hari tersisa:
- **Tier H-30**: Peringatan informatif kepada PIC operasional unit bisnis.
- **Tier H-14**: Peringatan penting kepada Kepala Departemen dan Legal Counsel.
- **Tier H-7**: Eskalasi tinggi kepada Finance Controller dan Direktur Operasional.
- **Tier H-1**: Notifikasi darurat pemenuhan kewajiban (SMS / push notification / outbox event).
- **Tier OVERDUE**: Pelanggaran kepatuhan hukum aktif; pembekuan transaksi modul terkait hingga dispensasi diterbitkan.

---

## 4. Prosedur Penanganan Insiden Operasional (Runbook SOP)

### 4.1 Insiden 1: Diskrepansi Rekonsiliasi Subledger (`ERR-SUBLEDGER-RECON`)
- **Tingkat Keparahan**: P1 (Memblokir proses tutup buku bulanan).
- **Gejala**: `enterprise:close-checklist` menghasilkan status `GAGAL` pada salah satu adapter (`ArSubledgerTotalAdapter`, `ApSubledgerTotalAdapter`, `FixedAssetSubledgerTotalAdapter`, atau `InventorySubledgerTotalAdapter`).
- **Langkah Investigasi**:
  1. Eksekusi audit diagnostik terperinci:
     ```bash
     php artisan enterprise:audit --entity=CORP-HOLDING
     ```
  2. Periksa baris keluaran yang menampilkan diskrepansi saldo:
     - Jika AR berselisih: periksa tabel faktur piutang pelanggan yang berstatus `PAID` tetapi belum memiliki ayat jurnal kas masuk di `bank_ledger_entries`.
     - Jika AP berselisih: periksa faktur tagihan pemasok yang memiliki selisih pembulatan sen atau PPh potong pungut gantung.
     - Jika Persediaan berselisih: periksa mutasi stok gudang WMS yang belum diposting ke jurnal nilai persediaan `wms:*`.
  3. Lakukan posting koreksi melalui service modul hulu yang bersangkutan. Dilarang melakukan manipulasi data langsung pada database!
  4. Jalankan ulang checklist verifikasi:
     ```bash
     php artisan enterprise:close-checklist 2026-10 --entity=CORP-HOLDING
     ```

### 4.2 Insiden 2: Kegagalan Reservasi Pagu Anggaran Modul Pengadaan (`ERR-BUDGET-EXHAUSTION`)
- **Tingkat Keparahan**: P2 (Mencegah penerbitan Purchase Order darurat).
- **Gejala**: Permintaan pembuatan PO dari modul Procurement gagal dengan pengecualian `BudgetGuardrailBreachException: Pelanggaran Hard-Stop: Permintaan reservasi Rp X melebihi sisa pagu bebas Rp Y`.
- **Langkah Investigasi**:
  1. Periksa utilisasi anggaran departemen terkait:
     ```bash
     php artisan enterprise:budget-sync --dry-run
     ```
  2. Evaluasi apakah terdapat komitmen gantung (*dangling reservations*) dari PO historis yang telah dibatalkan atau kedaluwarsa.
  3. Jika terdapat PO yang dibatalkan, jalankan service pembebasan komitmen:
     ```php
     app(BudgetCommitmentPort::class)->release($commitmentCode, 'Pembatalan PO darurat');
     ```
  4. Jika kebutuhan belanja riil memang melampaui alokasi RKAP tahunan:
     - Ajukan permohonan revisi anggaran melalui antarmuka `/enterprise-finance/budgets`.
     - Lewati alur persetujuan Maker-Checker resmi: `DRAFT -> SUBMITTED -> REVIEWED -> APPROVED`.
     - Sistem menerbitkan revisi ber-versi baru (misal: `BDG-2026-GEN-OPS-REV1`) dan memperbarui plafon pagu secara aman.

### 4.3 Insiden 3: Manipulasi Hash Manifest Bukti Audit (`ERR-TAMPERED-AUDIT-PACK`)
- **Tingkat Keparahan**: P1 (Integritas bukti audit eksternal tercemar).
- **Gejala**: Auditor melaporkan ketidakcocokan nilai hash SHA-256 antara berkas CSV yang diekstrak dengan berkas `manifest.json`.
- **Langkah Investigasi**:
  1. Verifikasi apakah ada modifikasi berkas di direktori sementara atau manipulasi manual pada basis data pasca penutupan periode.
  2. Buka catatan log `ef_financial_period_locks` untuk memastikan periode telah berstatus terkunci (`is_locked = 1`).
  3. Regenerasi paket bukti audit resmi dari basis data yang terkunci:
     ```bash
     php artisan enterprise:data-pack 2026-10 --entity=CORP-HOLDING --out=storage/audit_exports/reissued
     ```
  4. Periksa kecocokan hash baru dan serahkan kembali paket data terverifikasi kepada tim auditor independen bersama berita acara penerbitan ulang.

---

## 5. Rencana Pemulihan Bencana & Kesiapsiagaan Operasional (Disaster Recovery)

1. **Pencadangan Arsip Bukti Audit**: Setiap berkas ZIP paket data audit yang dihasilkan oleh `enterprise:data-pack` secara otomatis disimpan dalam penyimpanan objek terenkripsi (S3 / Cold Storage) dengan retensi minimal 10 tahun sesuai ketentuan perpajakan nasional.
2. **Kemandirian State Machine Anggaran**: Riwayat transisi status anggaran pada tabel `ef_budget_histories` bersifat *append-only*. Jika terjadi kehilangan koneksi atau crash sistem saat eksekusi persetujuan anggaran, transaksi dibatalkan secara atomik oleh database transaction wrapper (`DB::transaction`).
3. **Verifikasi Pasca Pemulihan Basis Data (Post-Restore Checklist)**:
   - Jalankan `php artisan test --filter=EnterpriseGovernancePhase54Test` untuk memastikan seluruh kontrak tata kelola berfungsi normal.
   - Jalankan `php artisan enterprise:audit` untuk memastikan saldo buku besar konsisten dengan seluruh subledger.
