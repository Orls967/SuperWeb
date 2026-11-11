# Kamus Galat & Penanganan Pengecualian (Exceptions): EnterpriseFinance

## 1. Daftar Pengecualian Domain (Domain Exceptions)

### `BudgetGuardrailBreachException`
- **Kode Pesan**: `EF-BUDGET-001`
- **Penyebab**: Percobaan mencadangkan komitmen pengadaan atau membelanjakan anggaran melebihi pagu yang tersedia pada mode hard-stop.
- **Tindakan**: Tingkatkan pagu anggaran melalui proses revisi resmi (`EnterpriseBudgetCycleStateMachine::STATUS_REVISED`) atau ajukan otorisasi soft-stop ke komite anggaran.

### `BudgetInvalidTransitionException`
- **Kode Pesan**: `EF-BUDGET-002`
- **Penyebab**: Upaya transisi status siklus hidup anggaran yang melanggar matriks transisi yang diizinkan (misal: langsung dari `DRAFT` ke `ACTIVE` tanpa persetujuan).
- **Tindakan**: Ikuti urutan status formal: `DRAFT` $\to$ `SUBMITTED` $\to$ `REVIEWED` $\to$ `APPROVED` $\to$ `ACTIVE`.

### `BudgetMakerCheckerViolationException`
- **Kode Pesan**: `EF-BUDGET-003`
- **Penyebab**: Operator pembuat anggaran (`created_by`) mencoba menyetujui anggarannya sendiri pada status `APPROVED`.
- **Tindakan**: Otorisasi persetujuan wajib dilakukan oleh operator berbeda dengan wewenang yang sah sesuai prinsip *Four-Eyes Principle*.

### `PeriodAlreadyLockedException`
- **Kode Pesan**: `EF-CLOSE-001`
- **Penyebab**: Percobaan menutup buku pada periode yang statusnya sudah terkunci di `ef_financial_period_locks` atau mencoba memposting transaksi baru ke periode terkunci.
- **Tindakan**: Gunakan periode fiskal berikutnya yang masih aktif untuk membukukan penyesuaian.

### `SubledgerReconciliationMismatchException`
- **Kode Pesan**: `EF-CLOSE-002`
- **Penyebab**: Penutupan buku ditolak karena saldo subledger (AR, AP, Fixed Asset, Inventory) tidak identik dengan saldo akun buku besar terkait.
- **Tindakan**: Selesaikan selisih subledger sebelum melakukan penutupan buku.

### `SodViolationException`
- **Kode Pesan**: `EF-SOD-001`
- **Penyebab**: Upaya pemberian hak akses atau penetapan peran ganda yang melanggar matriks 12 aturan pemisahan tugas tanpa dispensasi aktif.
- **Tindakan**: Hapus salah satu peran yang berkonflik, atau ajukan dispensasi berbatas waktu melalui form pengecualian SoD dengan persetujuan manajemen risiko.

### `InvalidAuditEvidenceManifestException`
- **Kode Pesan**: `EF-AUDIT-001`
- **Penyebab**: Verifikasi paket data bukti audit menemukan ketidakcocokan antara berkas CSV yang diarsipkan dengan checksum SHA-256 yang tercantum dalam manifest.
- **Tindakan**: Lakukan generate ulang data pack audit dan periksa integritas disk storage.
