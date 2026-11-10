# Runbook Operasional: Perbendaharaan & Keuangan Grup

Dokumen ini memuat prosedur operasional standar (SOP), jadwal eksekusi batch, penanganan insiden, pemulihan bencana (*disaster recovery*), dan checklist penutupan periode perbendaharaan untuk seluruh perintah artisan dan pekerjaan latar belakang domain Treasury.

---

## 1. Daftar Perintah Terjadwal & Konsol (Fase 48)

| Nama Perintah | Jadwal (Cron) | Sifat Idempotensi | Tujuan Utama |
|---|---|---|---|
| `treasury:fx-revalue {period}` | Bulanan (Hari terakhir 23:59) | Idempoten per `(period, lot_id)` | Menghitung dan memposting revaluasi kurs pos moneter valas sesuai PSAK 10. |
| `treasury:reverse-revaluations` | Bulanan (Hari pertama 00:05) | Idempoten per `revaluation_id` | Memposting jurnal pembalik otomatis revaluasi periode sebelumnya. |
| `treasury:import-statement` | On-demand / Harian 06:00 | Idempoten per `(account, ref, date, amount)` | Mengimpor mutasi rekening bank dari berkas CSV/MT940 simulasi. |
| `treasury:reconcile` | Harian 07:00 | Idempoten per statement | Menjalankan algoritma pemadanan rekonsiliasi bank 3-tahap (D2). |
| `treasury:mtm` | Harian 18:00 | Idempoten per `(date, contract_id)` | Mengevaluasi Mark-to-Market harian forward hedge contract. |
| `treasury:covenant-test` | Harian 08:00 & 20:00 | Read-only / Alert idempoten | Menguji seluruh covenant fasilitas kredit bank dan eskalasi. |
| `treasury:pool-sweep` | Harian 21:00 (Hari kerja) | Idempoten per `(pool, date)` | Melakukan zero-balancing sweep rekening anak ke induk. |
| `treasury:forecast` | Mingguan (Senin 05:00) | Idempoten per `(scenario, date)` | Menghasilkan ramalan likuiditas kas 13 minggu ke depan (D3). |
| `treasury:export-reports` | Mingguan / Akhir bulan | Idempoten per file output | Mengekspor 8 jenis laporan resmi perbendaharaan dalam format CSV. |
| `treasury:audit` | On-demand / Harian 23:00 | Read-only / Deterministic | Audit dua sumber tabel perbendaharaan vs ledger buku besar. |

---

## 2. Prosedur Pemulihan Insiden Spesifik (Recovery Playbooks)

### Skenario A: Revaluasi Menggunakan Kurs Salah / Keliru Input
- **Gejala**: Jurnal revaluasi akhir periode telah diposting ke ledger menggunakan kurs penutupan yang salah (misal salah ketik digit kurs).
- **Prosedur Pemulihan**:
  1. Jangan mengubah atau mengedit baris tabel revaluasi lama (prinsip append-only).
  2. Daftarkan versi kurs koreksi baru melalui `recordExchangeRate` dengan nomor versi yang lebih tinggi (`version + 1`).
  3. Jalankan kembali perintah:
     ```bash
     php artisan treasury:fx-revalue YYYY-MM --force-recalculate
     ```
  4. Sistem akan secara otomatis:
     - Memposting jurnal koreksi pembalik atas selisih kurs lama vs baru.
     - Memperbarui revaluasi snapshot dengan nomor versi kurs yang benar.
     - Memperbarui target jurnal pembalik awal periode berikutnya.

### Skenario B: Rekonsiliasi Bank Mengalami Kebuntuan (*Stuck*)
- **Gejala**: Terdapat baris mutasi bank yang salah terpadankan pada Tahap 2 (Toleransi) sehingga mutasi lain yang sebenarnya cocok menjadi terblokir.
- **Prosedur Pemulihan**:
  1. Buka antarmuka rekonsiliasi bank pada mutasi yang bermasalah.
  2. Klik tombol **Unmatch** dengan menyertakan alasan operasional resmi (misal: "Salah cocok toleransi; pasangan mutasi sebenarnya ada di batch berikutnya").
  3. Sistem membatalkan tautan rekonsiliasi dan mengembalikan status mutasi bank serta mutasi buku kas ke antrean `unmatched`.
  4. Jalankan kembali pemadanan manual atau re-run command:
     ```bash
     php artisan treasury:reconcile --account-id=<UUID>
     ```

### Skenario C: Kegagalan Jurnal Pembalik Awal Periode (*Reversal Failure*)
- **Gejala**: Perintah `treasury:reverse-revaluations` gagal berjalan pada tanggal 1 akibat database maintenance atau server restart.
- **Prosedur Pemulihan**:
  1. Jalankan perintah secara manual segera setelah server kembali aktif:
     ```bash
     php artisan treasury:reverse-revaluations --period=YYYY-MM
     ```
  2. Perintah ini aman dijalankan kapan saja karena bersifat idempoten penuh: sistem memeriksa bendera `is_reversed = false` dan kunci idempotensi `IDEM:TRS:REVAL_REV:{period}:{lot_id}` sebelum memposting.

### Skenario D: Diskrepansi pada Audit Dua Sumber (`treasury:audit` Keluar Kode $\ne 0$)
- **Gejala**: Perintah `php artisan treasury:audit` mengembalikan exit code 1 dan mencantumkan ketidakcocokan saldo rekening kas atau akun derivatif.
- **Prosedur Pemulihan**:
  1. Tinjau pesan keluaran audit untuk mengidentifikasi ID entitas atau akun ledger yang berselisih.
  2. Periksa apakah terdapat transaksi valas yang belum selesai diposting dua kaki (*unbalanced transaction*).
  3. Lakukan query saldo ledger akun terkait vs total subledger lot valas `trs_fx_lots`.
  4. Jika terdapat transaksi menggantung akibat kegagalan worker antrean, jalankan rekonsiliasi ulang dan kompensasi saldo.

### Skenario E: Peringatan Dini Pelanggaran Covenant Utang Bank
- **Gejala**: Notifikasi TaskInbox otomatis masuk mengindikasikan rasio DER mendekati plafon batas (> 90% dari `max_der_permil`).
- **Prosedur Pemulihan**:
  1. Segera hentikan seluruh permintaan penarikan fasilitas kredit (`DrawFacilityAction`) baru pada fasilitas terkait.
  2. Lakukan koordinasi dengan tim Enterprise Finance dan Tax untuk verifikasi posisi modal ekuitas entitas.
  3. Jika terdapat kas surplus di entitas anak lain, lakukan sweeping kas pool untuk mempercepat amortisasi utang berbunga tinggi.
  4. Bila drawdown mutlak dibutuhkan untuk operasional mendesak, mintakan surat penegasan *Covenant Waiver* resmi dari bank sindikasi sebelum memasukkan override approval di sistem.

### Skenario F: Penanganan Mutasi Rekening Koran Bank Korup / Tidak Lengkap
- **Gejala**: File mutasi bank format CSV/MT940 memiliki baris rusak atau checksum saldo awal dan akhir tidak seimbang.
- **Prosedur Pemulihan**:
  1. Parser mutasi bank akan menolak importasi secara atomik (*rollback all*) dan mencatat error ke tabel `trs_bank_recon_errors`.
  2. Minta berkas mutasi pengganti resmi dari portal perbankan e-banking institusi.
  3. Jalankan kembali importasi dengan flag validasi ketat:
     ```bash
     php artisan treasury:import-statement --path=/path/to/statement.csv --verify-balance
     ```

### Skenario G: Kegagalan Penyapuan Kas Notional Cash Pool (Sweep Block)
- **Gejala**: Perintah `treasury:pool-sweep` gagal memindahkan dana karena saldo rekening anak berada di bawah target balance minimum yang diizinkan regulator lokal.
- **Prosedur Pemulihan**:
  1. Sistem secara default menolak sweep yang menyebabkan saldo akhir negatif atau di bawah saldo pagu cadangan.
  2. Periksa batas minimum `target_balance` pada master cash pool `CashPoolAccount`.
  3. Jika terdapat kebutuhan likuiditas mendesak, ajukan transfer pinjaman bilateral antar-perusahaan (*intercompany loan*) khusus yang memiliki persetujuan dewan direksi anak perusahaan.

---

## 3. Checklist Pra-Penutupan Periode Treasury (Month-End Close Checklist)

Sebelum buku besar perbendaharaan ditutup pada akhir bulan buku, manajer Treasury wajib menyelesaikan dan menandatangani 10 langkah verifikasi berikut:

- [ ] **L-1. Kurs Penutupan Resmi**: Memastikan seluruh kurs penutupan valas (`CLOSING`) hari bursa terakhir telah terinput dan diverifikasi sesuai kurs tengah Bank Indonesia / JISDOR.
- [ ] **L-2. Rekonsiliasi Rekening Koran 100%**: Memastikan seluruh mutasi bank telah diimpor dan persentase keterpadanan mutasi mencapai $\ge 99\%$ (sisa pos tak tertandingi telah diinvestigasi).
- [ ] **L-3. Eksekusi Revaluasi Valas (D1)**: Menjalankan `RunMultiCurrencyFxRevaluationAction` untuk seluruh valas aktif dan memverifikasi sertifikat D1 ber-hash SHA-256.
- [ ] **L-4. Valuasi Mark-to-Market Lindung Nilai**: Memastikan seluruh kontrak forward aktif telah diperbarui nilai wajar MTM-nya pada tanggal penutupan.
- [ ] **L-5. Penyelesaian Kontrak Jatuh Tempo**: Memastikan kontrak forward yang jatuh tempo pada bulan berjalan telah berstatus `SETTLED` dan keuntungan/kerugian terealisasi telah dibukukan.
- [ ] **L-6. Verifikasi Kepatuhan Covenant DER**: Menjalankan pengujian covenant kredit bank; memastikan tidak ada fasilitas yang berstatus wanprestasi.
- [ ] **L-7. Penyapuan Akhir Bulan Kas Pool**: Mengeksekusi final monthly sweep untuk konsolidasi bunga intercompany pooling.
- [ ] **L-8. Rekonsiliasi Kas Kecil (Imprest)**: Memastikan seluruh voucher kas kecil telah diverifikasi dan diajukan penggantian dana (*replenishment*).
- [ ] **L-9. Ekspor Paket Laporan Resmi**: Mengekspor 8 berkas CSV laporan keuangan perbendaharaan ke arsip penyimpanan resmi menggunakan `treasury:export-reports`.
- [ ] **L-10. Verifikasi Audit Nol Selisih**: Menjalankan `php artisan treasury:audit` dan memastikan kode keluar adalah `0` (Zero Discrepancy).

---

## 4. Arsitektur Disaster Recovery & Single-Sign Integrity Check

Domain perbendaharaan menerapkan prinsip **Triple-Ledger Guarantee**:
1. **Ledger Immutability**: Baris jurnal yang telah diposting tidak pernah di-UPDATE atau di-DELETE. Koreksi hanya sah melalui jurnal pembalik (*reversing entry*).
2. **Single-Sign Netto Nol**: Seluruh transaksi yang melibatkan multi-mata uang wajib menyeimbangkan akun debit dan kredit secara tepat nol per kode mata uang.
3. **Idempotency Protection**: Setiap aksi finansial wajib menyertakan kunci idempotensi deterministik berformat `IDEM:{MODULE}:{ACTION}:{ENTITY_ID}:{TIMESTAMP_OR_HASH}`.
