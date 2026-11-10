# ADR-0035: Isolasi Transaksional Mode Sandbox dengan Verifikasi Probe Buku Besar (D35)

- **Status:** DITERIMA
- **Tanggal:** 2026-11-10
- **Pengambil Keputusan:** Tim Arsitektur Core Platform & Pelaksana W8-P3
- **Konteks:** Menjalankan simulasi skenario ekstrem tanpa kontaminasi data operasional basis data riil.

---

## 1. Konteks & Masalah

Pengujian skenario hipotetis (*what-if analysis*), seperti lonjakan gagal bayar atau kepanikan pasar, sering kali menghasilkan ratusan ribu mutasi data baris pada tabel-tabel operasional. Jika simulasi dilakukan langsung pada basis data operasional tanpa isolasi, membersihkan kembali data tiruan tersebut sangat sulit dan berisiko merusak integritas referensial.

## 2. Pilihan yang Dipertimbangkan

1. **Opsi A:** Membuat klona basis data fisik (*database cloning/branching*) setiap kali simulasi dijalankan. (DITOLAK: Membutuhkan hak akses infrastruktur tingkat tinggi, waktu penyalinan lama, dan pemborosan penyimpanan).
2. **Opsi B:** Menggunakan transaksi basis data ACID (`DB::beginTransaction()` / `DB::rollBack()`) dengan verifikasi probe saldo buku besar sebelum rollback, serta pencatatan metrik di luar konteks rollback. (DITERIMA).

## 3. Keputusan

Kami memutuskan bahwa opsi `--mode=sandbox` membungkus seluruh eksekusi bisnis dalam transaksi basis data:
1. Dimulai dengan `DB::beginTransaction()`.
2. Seluruh mutasi domain dieksekusi secara normal (termasuk posting ledger oleh Action modul).
3. Sebelum transaksi diakhiri, `ProbeLedgerBalanceAction` memverifikasi invarian fundamental:
   $$\sum \text{Debit} - \sum \text{Kredit} = 0$$
   Jika tidak seimbang, sistem melempar galat dan membatalkan status valid.
4. Transaksi di-rollback secara deterministik melalui `DB::rollBack()`.
5. Ringkasan hasil, status selesai, dan fingerprint akhir dipersistensikan ke tabel `sim_runs` setelah rollback.

## 4. Konsekuensi

- **Positif:** Nol kontaminasi data operasional (*zero data leakage*). Keseimbangan akuntansi terbukti secara matematis.
- **Negatif:** Sesi sandbox tidak dapat menggunakan koneksi multi-database yang tidak mendukung distributed two-phase commit (2PC) tanpa konfigurasi koordinasi khusus.

