# Panduan Peran: Dokter DPJP & Klinisi (Fase 87)

Panduan operasional harian untuk peran `doctor` di modul Rumah Sakit (Hospital).

## 1. Rute & Akses
- `/hospital/er`: Papan gawat darurat (ER Board), triase pasien, asesmen awal.
- `/hospital/critical-care`: Monitor telemetri ICU/HDU dan respons darurat Code Blue.
- `/hospital/patients/{id}/passport`: Pemeriksaan paspor medis terenkripsi (riwayat alergi, imunisasi, diagnosa kronis, pembedahan).

## 2. Prosedur Tugas Harian

### 2.1 Evaluasi Pasien & Pembuatan Clinical Pathway Orders
1. Buka detail pasien melalui antrean bangsal atau IGD.
2. Tentukan paket Clinical Pathway berdasarkan diagnosis ICD-10.
3. Terbitkan order medis berurutan (laboratorium, radiologi, prosedur, obat).
4. Setiap order diberi estimasi durasi dan urutan ketergantungan. Order pertama yang terlambat > 15 menit akan memicu notifikasi peringatan ke perawat jaga secara otomatis.

### 2.2 Tanggap Darurat Code Blue
1. Saat alarm Code Blue berbunyi di bangsal, dokter jaga menerima notifikasi eskalasi pada menit ke-2 apabila belum di-acknowledge perawat.
2. Segera konfirmasi penerimaan (`Acknowledge Code Blue`) pada layar `/hospital/critical-care`.
3. Pimpin tindakan resusitasi jantung paru (RJP) atau stabilisasi jalan napas.
4. Setelah kondisi pasien stabil atau dipindahkan ke ICU, tandai status sebagai `Resolved` dengan mencantumkan catatan medis dan kode tindakan. Seluruh rangkaian waktu tercatat permanen pada bukti hash chain.

## 3. Efek Terlihat pada Buku Besar & Sistem
- Order tindakan dokter yang diselesaikan mencatatkan baris item folio siap tagih.
- Pemeriksaan paspor medis mencatatkan log audit akses pada `hsp_data_vault_accesses` untuk mematuhi UU PDP.
