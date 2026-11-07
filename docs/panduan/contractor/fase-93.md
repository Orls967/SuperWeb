# Panduan Operasional Peran: Kontraktor Penambangan (`contractor`) — Fase 93

## 1. Hak Akses & Isolasi Data
Kontraktor hauling memiliki portal mandiri terisolasi di `/contractor/mining`. Melalui portal ini, kontraktor hanya dapat melihat armada, siklus hauling, dan kasus hold bahan bakar milik entitas bisnisnya sendiri (pencegahan IDOR multi-tenant).

## 2. Alur Tugas & Pengajuan Sanggahan (Dispute)
1. **Memeriksa Status Tagihan & Penahanan Dana**:
   - Buka `/contractor/mining`.
   - Periksa tabel **Daftar Hold Tagihan (Escrow / Deductions)** untuk melihat rincian pemotongan sementara tagihan hauling akibat deviasi bahan bakar.

2. **Mengajukan Sanggahan Anomali BBM**:
   - Pada tabel **Kasus Anomali BBM Kontraktor**, temukan kasus yang statusnya `PAYMENT_HELD`.
   - Masukkan alasan sanggahan yang sah pada kolom form (misal: "Rute hauling Pit Barat - ROM mengalami degradasi jalan akibat hujan 40 mm, kecepatan truk tertahan di gigi rendah sehingga pemakaian solar meningkat").
   - Klik tombol **Sanggah**. Status kasus akan bertransisi menjadi `DISPUTED` dan diteruskan ke tim Finance Officer untuk ditinjau ulang.

3. **Memantau Keputusan Akhir**:
   - Jika sanggahan diterima, status kasus akan berubah menjadi `RESOLVED_RELEASED` dan dana hold dikembalikan penuh ke saldo piutang kontraktor.
   - Jika sanggahan ditolak, status berubah menjadi `RESOLVED_DEDUCTED` dengan catatan penjelasan dari Finance Officer.
