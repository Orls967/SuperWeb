# Runbook Operasional — Tata Kelola & Siklus Hidup Waiver Arsitektur

## 1. Ikhtisar Kebijakan Waiver (ADR-0043, DR4-1)
Waiver arsitektur adalah dispensasi teknis terbatas waktu bagi kode warisan (*legacy code*) yang melanggar batas modular (aturan A1 atau A2). Setiap waiver wajib mematuhi ketentuan tata kelola ketat:
- **Tenggat Waktu Maksimum**: $\le 90$ hari sejak persetujuan.
- **Rujukan Wajib**: Anchor keputusan teknis di `docs/DECISIONS.md#w8-d43` atau keputusan terkait yang sah.
- **Persetujuan Ganda**: Disetujui oleh Platform Architect dan Pemilik Modul terkait via `ApprovalEngine`.
- **Riwayat Lengkap**: Dicatat dalam tabel append-only `core_boundary_violation_transitions`.

---

## 2. Prosedur Permohonan & Persetujuan Waiver

### 2.1 Mengajukan Permohonan Waiver
Pengembang atau arsitek mengajukan waiver melalui command atau antarmuka admin:
1. Akses halaman `/admin/architecture/violations` atau detail pelanggaran.
2. Klik tombol **Ajukan Waiver**.
3. Isi alasan teknis, rujukan ADR anchor (misal `docs/DECISIONS.md#w8-d43`), dan tanggal berakhir ($\le 90$ hari).
4. Tindakan ini memicu `WaiverApprovalBridge::requestWaiverApproval()` yang menerbitkan tiket di `ApprovalEngine`.

### 2.2 Persetujuan oleh Pemilik Modul
1. Pemilik modul menerima tugas persetujuan di Kotak Masuk "Tugas Saya".
2. Setelah disetujui, status pelanggaran beralih dari `triaged` ke `waived`.
3. Event `core.boundary.waiver_granted.v1` dicatat ke Outbox Bus.

---

## 3. Prosedur Kedaluwarsa Otomatis & Remediasi

### 3.1 Pengecekan Pra-Kedaluwarsa (H-7)
1. Command harian mendeteksi waiver yang tersisa $\le 7$ hari masa berlaku.
2. `WaiverApprovalBridge::sweepExpiringWaivers()` menerbitkan tugas prioritas tinggi ke Kotak Masuk arsitek.
3. Notifikasi dikirimkan untuk mempersiapkan migrasi kontrak sebelum kedaluwarsa.

### 3.2 Eksekusi Kedaluwarsa Otomatis
1. Cron harian menjalankan:
   ```bash
   php artisan boundary:expire-waivers
   ```
2. Seluruh waiver yang melewati `expires_at` otomatis dialihkan dari `waived` kembali ke `triaged`.
3. Event `core.boundary.waiver_expired.v1` diterbitkan.
4. Jika audit `boundary:audit` dijalankan sebelum perpanjangan/remediasi, audit akan gagal mendeteksi waiver kedaluwarsa.

---

## 4. Prosedur Perpanjangan Waiver (Extension Workflow)
Jika refaktor kontrak membutuhkan waktu tambahan dan masih dalam batas toleransi platform:
1. Arsitek mengajukan perpanjangan melalui `admin.boundaries.waivers.extend`.
2. Perpanjangan maksimal 30 hari tambahan (total kumulatif tidak boleh melebihi 180 hari).
3. Catatan transisi mendokumentasikan alasan perpanjangan dan pemohon.

