# PANDUAN OPERASIONAL DAN RUNBOOK ARMADA KAPAL NIAGA, VOYAGE & LAYANAN MARITIM

**Kode Dokumen:** `SOP-PRT-FLT-001`  
**Klasifikasi:** Manual Operasional Standar (Lini 27 & Lini 22)  
**Status:** `🔵 AKTIF`  

---

## 1. Ikhtisar & Peran Pengguna

Panduan ini mengatur prosedur standar operasional (SOP) untuk:
- **Marine Fleet Superintendent:** Memantau kelaiklautan kapal, audit sertifikat SOLAS/MARPOL, dan pengawasan Safe Manning STCW.
- **Master & Chief Engineer (Nakhoda & KKM):** Pelaporan tengah hari (*Noon Reports*), navigasi rute aman, dan pematuhan aturan air ballas IMO D-1/D-2.
- **Chartering & Commercial Manager:** Kesepakatan fixture perjanjian sewa kapal (Gencon/NYPE), negosiasi tarif, dan audit laytime/demurrage.
- **Port Operations Agent:** Pengajuan pandu tunda, reservasi jendela dermaga (K27), pengisian bunker (BDN), dan penyelesaian akun pengeluaran pelabuhan (PDA/FDA).

---

## 2. Prosedur Standar Kelaiklautan Kapal (Seaworthiness Protocol)

Sebelum sebuah kapal niaga diizinkan menerima pesanan muatan atau memulai pelayaran baru, langkah-langkah verifikasi berikut wajib dipenuhi:

1. **Pemeriksaan Masa Berlaku Sertifikat Statutoria:**
   - *Cargo Ship Safety Construction Certificate* (masa berlaku 5 tahun, endorsement tahunan).
   - *Cargo Ship Safety Equipment Certificate* (masa berlaku 2 tahun).
   - *International Oil Pollution Prevention (IOPP) Certificate* (MARPOL Annex I).
   - *Safety Management Certificate (SMC)* di bawah kerangka ISM Code.
   - *International Ship Security Certificate (ISSC)* di bawah ISPS Code.
   - *Maritime Labour Certificate (MLC)* di bawah konvensi ILO MLC 2006.
   - Sistem akan menolak penerbitan jadwal pelayaran secara otomatis jika terdapat satu sertifikat yang kedaluwarsa.

2. **Kepatuhan Safe Manning Document (STCW 1978/2010):**
   - Komposisi awak di atas kapal wajib memenuhi kuota minimum:
     - 4 Perwira Dek (Master, Chief Mate, 2nd Mate, 3rd Mate).
     - 4 Perwira Mesin (Chief Engineer, 2nd Engineer, 3rd Engineer, 4th Engineer).
     - 6 Rating Dek (Bosun, Able Seaman, Ordinary Seaman).
     - 4 Rating Mesin (Fitter, Oiler, Wiper).
     - 2 Awak Katering (Chief Cook, Messman).
   - Setiap pelaut wajib memiliki Buku Pelaut (*Seaman's Book*) dan Sertifikat Kesehatan Maritim yang masih berlaku.

3. **Verifikasi Cadangan Bahan Bakar (ROB):**
   - Tangki bahan bakar kapal wajib memiliki cadangan aman (*safety reserve*) minimal 10% di atas estimasi kebutuhan bahan bakar pelayaran penuh menuju pelabuhan tujuan.

---

## 3. Alur Pelaksanaan Pelayaran & Pelaporan Harian (Noon Reports)

1. **Tender Notice of Readiness (NOR):**
   - Nakhoda wajib menerbitkan NOR segera setelah kapal tiba di batas perairan pelabuhan muat/bongkar (*at or off the port / anchorage*) dan kapal telah siap dalam segala hal (*ready in all respects*).
   - NOR ditenderkan melalui sistem modul Port atau email resmi agensi dengan stempel waktu UTC.

2. **Pengisian Laporan Tengah Hari (Noon Report Routine):**
   - Setiap hari tepat pukul 12:00 waktu setempat, perwira navigasi dan masinis jaga menginput data Noon Report:
     - Posisi koordinat GPS kapal (Latitude dan Longitude).
     - Jarak tempuh 24 jam terakhir dalam mil laut (NM).
     - Kecepatan rata-rata GPS dan log air (*speed through water*).
     - Keadaan cuaca: skala angin Beaufort (0–12), arah angin, dan tinggi gelombang Douglas (skala 0–9).
     - Putaran mesin utama rata-rata (RPM) dan persentase slip baling-baling (*propeller slip percentage*).
     - Konsumsi bahan bakar VLSFO dan LSMGO 24 jam terakhir (dalam metrik ton).
     - Sisa bahan bakar di atas kapal (*Remaining On Board* / ROB).
   - Bila persentase slip baling-baling melebihi 8.0%, sistem akan memunculkan bendera peringatan (*Warning Alert*) potensi hambatan hidrodinamis atau cuaca buruk.

3. **Mitigasi Rute Cuaca Buruk (Weather Routing Protocol):**
   - Bila modul *Weather Routing* mendeteksi prakiraan gelombang signifikan ($H_s \ge 4.0$ meter) di sepanjang rute langsung kapal, nakhoda wajib mengevaluasi saran rute deviasi (*Weather Avoidance Profile*).
   - Penerimaan deviasi rute secara otomatis memperbarui jarak rencana pelayaran dan menyesuaikan alokasi bahan bakar bunker kapal.

---

## 4. SOP Perhitungan Laytime & Sengketa Demurrage

1. **Pengumpulan Dokumen Statement of Facts (SOF):**
   - Agen pelabuhan menyusun rekaman kejadian SOF yang mencatat jam-jam penting:
     - Waktu tender NOR dan waktu penerimaan NOR.
     - Waktu kapal merapat dan sandar di dermaga (*All Fast*).
     - Waktu dimulainya pekerjaan pemuatan/pembongkaran kargo.
     - Catatan interupsi cuaca: jam mulai dan jam berhenti hujan lebat.
     - Catatan kerusakan alat: derek kapal (*crane breakdown*) vs derek dermaga (*shore gantry crane failure*).
     - Waktu penyelesaian pemuatan dan penyerahan konosemen B/L.

2. **Eksekusi LaytimeDemurrageCalculator:**
   - Jalankan fungsi perhitungan otomatis melalui menu komersial modul Port atau panggil action `IssueDemurrageDespatchInvoiceAction`.
   - Sistem akan mengevaluasi apakah waktu yang diizinkan (*allowed laytime*) mencukupi:
     - Jika pekerjaan selesai lebih cepat, terbitkan kredit despas (*Despatch Note*).
     - Jika pekerjaan melampaui waktu yang diizinkan, terbitkan faktur denda *Demurrage Invoice* dengan jatuh tempo 14 hari.

3. **Eskalasi Sengketa Komersial:**
   - Jika penyewa mengajukan nota keberatan (*Letter of Protest*), tandai status fixture sebagai `DISPUTED`.
   - Siapkan berkas audit SOF, rekam telemetri GPS kapal, dan log cuaca BMKG/NOAA untuk diserahkan ke majelis arbitrase maritim (SCMA atau LMAA).

---

## 5. Prosedur Penanganan Darurat Bencana Laut & General Average

Bila kapal menghadapi ancaman bahaya bersama nyata di laut lepas (badai ekstrem monsun, kebakaran palka muatan, atau kandas di selat sempit):

1. **Tindakan Penyelamatan Nakhoda (Perils of the Sea):**
   - Nakhoda memiliki wewenang hukum penuh untuk mengambil keputusan luar biasa demi keselamatan bersama seluruh jiwa dan properti (misal melakukan *jettison* kargo dek untuk stabilitas, memaksa mesin bermanuver darurat, atau menandatangani kontrak bantuan kapal tunda penyelamat *Lloyd's Open Form* / LOF).

2. **Pernyataan Deklarasi General Average:**
   - Segera setelah kapal tiba di pelabuhan perlindungan (*port of refuge*), pemilik kapal mendeklarasikan General Average di bawah *York-Antwerp Rules 2016*.
   - Tunjuk kantor penilai kerugian maritim terakreditasi (*Average Adjuster*, misal Richards Hogg Lindley).

3. **Perhitungan & Penjaminan Kargo (Average Bond & Guarantee):**
   - Seluruh pemilik kargo wajib menyerahkan *Average Bond* dan jaminan asuransi kargo (*Underwriter's Guarantee*) sebelum kargo diizinkan dibongkar di pelabuhan tujuan.
   - Layanan `GeneralAverageAdjustmentService` menghitung pembagian kontribusi proporsional antara nilai kapal, kargo, dan uang tambang, serta membukukannya di buku besar armada maritim.

---

## 6. Prosedur Audit Integritas Buku Besar (`prt:fleet-audit`)

1. Setiap akhir bulan kalender atau sebelum penutupan buku pelayaran, staf keuangan maritim wajib menjalankan command audit integritas:
   ```bash
   php artisan prt:fleet-audit
   ```
2. Pastikan keluaran terminal menunjukkan:
   - Status Keseimbangan: `SEIMBANG` ($\text{Total Debit} = \text{Total Credit}$).
   - Entri Rusak / Corrupt: `0`.
   - Exit code: `0`.
3. Bila ditemukan selisih atau korupsi data, periksa log transaksi yang dicatat pada tabel `prt_fleet_ledger_entries` dan lakukan rekonsiliasi bersama divisi agensi pelabuhan.
