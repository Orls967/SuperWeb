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


---

## 4. Evaluasi Kesiapan Pemulangan Pasien (Discharge Readiness)
Sebelum menandatangani resume medis pemulangan (*discharge summary*), dokter DPJP wajib menjalankan asesmen kesiapan via `PatientDischargeReadinessScorer`:
1. **Pemeriksaan 7 Kriteria Kelayakan**:
   - Tanda vital stabil tanpa demam dalam 24 jam terakhir (NEWS2 $\le 2$).
   - Toleransi obat dan nutrisi per oral penuh.
   - Nyeri terkontrol dengan obat minum (skor VAS $\le 3$).
   - Bebas alat invasif yang tidak diperlukan di rumah.
   - Hasil pemeriksaan laboratorium kritis (kultur darah, marker jantung, fungsi ginjal) telah ditinjau dan divalidasi.
   - Keluarga/caregiver telah mendapatkan edukasi perawatan lanjutan dan jadwal kontrol ulang.
2. **Kalkulasi & Verifikasi**:
   - Tekan tombol **Hitung Skor Kesiapan KRS**.
   - Jika skor $\ge 80\%$ dan tanpa *blocking reason*, sistem menerbitkan sertifikat kesiapan KRS dan mengaktifkan tombol pelepasan ranjang.
   - Apabila terdapat *hard blocker* (misal: hasil lab kritis belum dibaca), sistem mengunci proses pemulangan demi keselamatan pasien.

## 5. Kepemimpinan Resusitasi Lanjutan (ACLS Code Blue)
1. **Peran Tim Leader**:
   - Dokter memimpin resusitasi dengan komunikasi tertutup (*closed-loop communication*).
   - Menugaskan perawat 1 (kompresi dada bergantian tiap 2 menit), perawat 2 (ventilasi bag-valve-mask + oksigen 100%), perawat 3 (akses IV/IO & obat-obatan), dan pencatat waktu (*timekeeper*).
2. **Algoritma Irama Jantung**:
   - **VF / Pulseless VT (Shockable)**: Segera kejut listrik bifasik 200 Joule, langsung lanjutkan CPR 2 menit tanpa periksa nadi, pasang akses IV, berikan epinefrin 1 mg setelah kejut kedua, amiodaron 300 mg bolus setelah kejut ketiga.
   - **Asystole / PEA (Non-Shockable)**: CPR berkualitas tinggi, berikan epinefrin 1 mg segera, evaluasi penyebab 5H dan 5T (Hipovolemia, Hipoksia, Hidrogen ion/asidosis, Hipo/hiperkalemia, Hipotermia; Tension pneumothorax, Tamponade jantung, Toksin, Trombosis paru, Trombosis koroner).
3. **Dokumentasi Terverifikasi**:
   - Setelah resusitasi selesai, dokter mengisi ringkasan tindakan resusitasi.
   - Sistem merekonstruksi kronologi via `CriticalIncidentReplayEngine` untuk jaminan mutu mediko-legal.
