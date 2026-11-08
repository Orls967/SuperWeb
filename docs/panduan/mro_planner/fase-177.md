# Panduan Pengguna: MRO Planner & Pemeliharaan Armada (Fase 177)

Panduan ini ditujukan bagi **MRO Planner (`mro_planner`)** dan Chief Engineer dalam merencanakan inspeksi teknis armada, penjadwalan perawatan berkala, serta integrasi perintah kerja ke modul Asset (K9).

---

## 1. Pemantauan Siklus & Jam Terbang Armada Pesawat

Akses antarmuka armada melalui rute `/aviation/fleet`:
1. **Daftar Armada (Fleet Registry):**
   - Menampilkan seluruh nomor registrasi pesawat (misal: `PK-GAA`, `PK-GIA`).
   - Jam terbang sejak A-Check terakhir (*hours since last A-check*).
   - Siklus lepas landas / pendaratan sejak C-Check terakhir (*cycles since last C-check*).
   - Tanggal kedaluwarsa sertifikat kelaikan udara (*Certificate of Airworthiness* / CoA).
2. **Kriteria Batas Kelaikan:**
   - **A-Check:** Wajib dilakukan sebelum mencapai batas interval (misal: 600 jam terbang).
   - **C-Check:** Wajib dilakukan sebelum mencapai batas siklus (misal: 3.000 siklus).
   - Pesawat yang mendekati ambang batas toleransi (sisa < 50 jam) akan diberi label peringatan visual kuning.

---

## 2. Pembuatan Jadwal Perawatan & Integrasi Modul Asset (K9)

Ketika pesawat dijadwalkan masuk hanggar:
1. Pilih armada pada tabel registrasi.
2. Tentukan jenis inspeksi: `A_CHECK`, `C_CHECK`, `D_CHECK`, atau `LINE_CHECK`.
3. Masukkan stasiun MRO (misal: `CGK-GMF`, `SUB-MERPATI`, `DPS-BALI-MRO`).
4. Tentukan estimasi tanggal mulai dan tanggal selesai perawatan.
5. Tekan tombol **Jadwalkan Kunjungan Perawatan**.
6. Sistem secara otomatis:
   - Membuat rekaman `AviationMaintenanceVisit` dengan status awal `planned`.
   - Mengirim permintaan pembuatan perintah kerja (*Work Order*) ke modul Asset melalui kontrak `MaintenanceWorkOrderRequester` (K9).
   - Mencatat estimasi provisi beban pemeliharaan pada akun buku besar `avi:maintenance_expense:IDR` dan hutang usaha `avi:maintenance_payable:IDR`.

---

## 3. Rilis Kelaikan Terbang (Release to Service / RTS)

Setelah tim teknisi MRO menyelesaikan seluruh inspeksi dan menutup buku catatan ketidaksesuaian (*discrepancy log*):
1. Buka rincian kunjungan perawatan.
2. Masukkan nomor lisensi teknisi inspektur (*Aircraft Maintenance Engineer* / AME).
3. Tekan tombol **Release to Service (RTS)**.
4. Sistem akan:
   - Mereset pencacah jam terbang atau siklus pendaratan ke angka nol sesuai jenis inspeksi yang diselesaikan.
   - Memperpanjang masa berlaku sertifikat kelaikan udara secara otomatis di basis data.
   - Mengubah status armada dari *maintenance* kembali ke *active* sehingga pesawat siap dialokasikan untuk jadwal penerbangan komersial berikutnya.

