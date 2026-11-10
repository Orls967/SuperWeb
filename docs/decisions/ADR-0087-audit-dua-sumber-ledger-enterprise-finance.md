# ADR-0087: Audit Dua Sumber Terpadu Antara Buku Besar Perbankan dan Modul EnterpriseFinance

**Status**: DITERIMA  
**Tanggal**: 2026-11-11  
**Pencetus**: Arsitek Solusi Keuangan Grup [W8-P5.3]  
**Konteks**: Modul `EnterpriseFinance` (Fase 54)

---

## 1. Konteks Bisnis & Masalah
Data yang tercatat pada modul EnterpriseFinance (anggaran, realisasi, dividen anak, penarikan pinjaman sindikasi, pembayaran bunga) rentan terdegradasi integritasnya jika terjadi perubahan langsung pada database tanpa melewati alur pembukuan resmi. Diperlukan audit dua sumber (*dual-source audit*) yang memverifikasi sinkronisasi antara catatan operasional dan jurnal buku besar perbankan (`bank_ledger_transactions`).

## 2. Keputusan yang Diambil
1. **Artisan Command `enterprise:audit` Terjadwal**:
   - Mengeksekusi verifikasi integritas menyeluruh secara otomatis setiap pergantian periode dan dapat dipanggil secara manual oleh auditor.
2. **5 Area Rekonsiliasi Independen**:
   - Area 1: Invarian Anggaran (Total Encumbered + Spent $\le$ Allocated pada tipe HARD_STOP).
   - Area 2: Invarian Pajak Nasional (PPN payable $\equiv$ max(0, Output - Input), PPh dipotong $\equiv$ Withheld Tax).
   - Area 3: Invarian Covenant Utang (Semua evaluasi rasio covenant memiliki sertifikat hash valid yang diverifikasi oleh `SyndicatedLoanCovenantCertificateVerifier`).
   - Area 4: Keseimbangan Agregasi Trial Balance (Total debit $\equiv$ total kredit pada seluruh laporan konsolidasi aktif).
   - Area 5: Rekonsiliasi Buku Besar Dua Sumber (Total saldo belanja yang tercatat di tabel `ef_budgets` harus cocok dengan mutasi neto di akun `ef:department_expense:IDR` pada `bank_ledger_accounts`).
3. **Pemberlakuan Status Kegagalan Tegas**:
   - Jika ditemukan diskrepansi sekecil apa pun ($\Delta \neq 0$), command exit dengan kode 1 (`FAILURE`) dan mencatat log rincian pelanggaran. Jika bersih, exit dengan kode 0 (`SUCCESS`).

## 3. Konsekuensi
- Mencegah *drift* antara modul operasional keuangan korporat dan pencatatan buku besar resmi; memberikan jaminan audit yang tak terbantahkan bagi auditor eksternal.
