# Runbook Operasional: Perbendaharaan & Keuangan Grup

Dokumen ini memuat prosedur operasional standar (SOP), jadwal eksekusi batch, penanganan insiden, dan pemulihan bencana (*disaster recovery*) untuk perintah artisan dan pekerjaan latar belakang domain Treasury.

---

## 1. Daftar Perintah Terjadwal & Konsol (Fase 48)

| Nama Perintah | Jadwal (Cron) | Sifat Idempotensi | Tujuan Utama |
|---|---|---|---|
| `treasury:fx-revalue {period}` | Bulanan (Hari terakhir 23:59) | Idempoten per `(period, lot_id)` | Menghitung dan memposting revaluasi kurs pos moneter valas. |
| `treasury:reverse-revaluations` | Bulanan (Hari pertama 00:05) | Idempoten per `revaluation_id` | Memposting jurnal pembalik otomatis revaluasi periode sebelumnya. |
| `treasury:import-statement` | On-demand / Harian 06:00 | Idempoten per `(account, ref, date, amount)` | Mengimpor mutasi rekening bank dari berkas CSV/MT940. |
| `treasury:reconcile` | Harian 07:00 | Idempoten per statement | Menjalankan algoritma pemadanan rekonsiliasi bank 3-tahap. |
| `treasury:mtm` | Harian 18:00 | Idempoten per `(date, contract_id)` | Mengevaluasi Mark-to-Market harian forward hedge contract. |
| `treasury:covenant-test` | Harian 08:00 & 20:00 | Read-only / Alert idempoten | Menguji seluruh covenant fasilitas kredit bank dan eskalasi. |
| `treasury:pool-sweep` | Harian 21:00 (Hari kerja) | Idempoten per `(pool, date)` | Melakukan zero-balancing sweep rekening anak ke induk. |
| `treasury:forecast` | Mingguan (Senin 05:00) | Idempoten per `(scenario, date)` | Menghasilkan ramalan likuiditas kas 13 minggu ke depan. |
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
     php artisan treasury:reverse-revaluations
     ```
  2. Perintah ini aman dijalankan kapan saja karena bersifat idempoten penuh: sistem memeriksa bendera `is_reversed = false` dan kunci idempotensi `IDEM:TRS:REVAL_REV:{period}:{lot_id}` sebelum memposting.

### Skenario D: Diskrepansi pada Audit Dua Sumber (`treasury:audit` Keluar Kode $\ne 0$)
- **Gejala**: Perintah `php artisan treasury:audit` mengembalikan exit code 1 dan mencantumkan ketidakcocokan saldo rekening kas atau akun derivatif.
- **Prosedur Pemulihan**:
  1. Tinjau pesan keluaran audit untuk mengidentifikasi ID entitas atau akun ledger yang berselisih.
  2. Periksa apakah terdapat transaksi valas yang belum selesai diposting dua kaki (*unbalanced transaction*).
  3. Lakukan query saldo ledger akun terkait vs total subledger lot valas `trs_fx_lots`.
  4. Jika terdapat transaksi menggantung akibat kegagalan worker antrean, jalankan rekonsiliasi ulang dan kompensasi saldo.
