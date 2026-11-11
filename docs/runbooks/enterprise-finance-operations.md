# Runbook Operasional: Command Terjadwal & Pemulihan Insiden EnterpriseFinance

## 1. Katalog Command Terjadwal & Rutinitas Sistem

| Nama Command | Frekuensi | Parameter | Idempotensi | Dampak Bisnis |
|---|---|---|---|---|
| `enterprise:budget-sync` | Tiap Jam | `--dry-run` | Ya (kunci hash komitmen) | Menyinkronkan komitmen PO Procurement ke `ef_budget_encumbrances`. |
| `enterprise:close-checklist` | Akhir Bulan | `{period} {--entity=}` | Ya (kunci entity+period) | Menguji prasyarat rekonsiliasi sebelum penutupan buku permanen. |
| `enterprise:compliance-escalate` | Harian 07:00 | `--as-of=` | Ya (kunci deadline+tier) | Mengirim notifikasi eskalasi H-30 s/d Overdue ke penanggung jawab. |
| `enterprise:control-tests` | Harian 01:00 | `--sample-size=` | Ya (kunci tanggal uji) | Menjalankan uji otomatis RCM ICFR dan menerbitkan CoreTask saat defisiensi. |
| `enterprise:data-pack` | Sesuai Kebutuhan | `{period} {--entity=} {--out=}` | Ya (output deterministik) | Menghasilkan bundel ZIP bukti audit eksternal + manifest SHA-256. |
| `enterprise:sod-scan` | Harian 02:00 | `--strict` | Ya (kunci scan harian) | Memindai konflik SoD aktif dan mencatat log anomali. |
| `enterprise:tax-recon` | Bulanan | `{period} {--entity=}` | Ya (kunci entity+period) | Merekonsiliasi PPN masukan vs keluaran dan nomor faktur gapless. |
| `enterprise:audit` | Harian 04:00 | `{--entity=} {--period=}` | Ya (read-only audit) | Audit dua sumber 10 dimensi (buku besar vs subledger & fakta). |

## 2. Prosedur Penanganan Insiden & Pemulihan (Troubleshooting)

### A. Selisih Rekonsiliasi Subledger saat Tutup Buku (`SUBLEDGER_DISCREPANCY`)
- **Gejala**: Command `enterprise:close-checklist` gagal dengan exit code 1 dan pesan diskrepansi saldo subledger.
- **Penyebab**: Terdapat transaksi di modul Accounts Receivable / Accounts Payable / Inventory yang belum selesai dijurnal atau mengalami kegagalan posting.
- **Tindakan Pemulihan**:
  1. Jalankan `php artisan enterprise:audit --entity={KODE}` untuk mengidentifikasi modul yang berselisih.
  2. Buka subledger terkait dan telusuri transaksi gantung (*unposted items*).
  3. Lakukan posting perbaikan melalui alur resmi modul hulu.
  4. Jalankan ulang `enterprise:close-checklist`.

### B. Komitmen Anggaran Gantung (*Dangling Encumbrance*)
- **Gejala**: Pagu anggaran terpotong namun Purchase Order terkait di Procurement berstatus dibatalkan atau ditolak.
- **Penyebab**: Event pelepasan komitmen gagal diproses atau terputus jaringan.
- **Tindakan Pemulihan**:
  1. Jalankan `php artisan enterprise:budget-sync --fix`.
  2. Command akan mendeteksi PO yang dibatalkan dan memanggil `BudgetCommitmentPort::release()`.
  3. Status encumbrance berubah menjadi `RELEASED` dan pagu bebas pulih otomatis.

### C. Periode Terkunci Menerima Upaya Mutasi (`PERIOD_LOCKED_MUTATION`)
- **Gejala**: Pemakai atau integrasi luar mencoba memposting mutasi dengan tanggal yang berada dalam periode yang telah dikunci di `ef_financial_period_locks`.
- **Tindakan Pemulihan**:
  1. Jangan membuka kunci periode yang telah dilaporkan ke regulator!
  2. Mutasi penyesuaian harus dicatat pada periode buku berjalan yang masih terbuka dengan referensi periode asal (*prior period adjustment*).

