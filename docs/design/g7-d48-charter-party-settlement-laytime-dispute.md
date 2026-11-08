# KEPUTUSAN ARSITEKTUR G7-D48: MEKANISME PENYELESAIAN SENGKETA SEWA KAPAL & KALKULASI LAYTIME (BIMCO GENCON / LMAA)

**Status:** `🔵 DISETUJUI & DIIMPLEMENTASIKAN`  
**Fase:** Fase 180 (Armada Kapal, Voyage, Charter & Marine Services)  
**Terkait:** Lini 27 (Maritim & Pelabuhan), Kontrak G7-K27, Algoritma A11 (`LaytimeDemurrageCalculator`)  

---

## 1. Konteks Bisnis & Masalah

Dalam operasional pelayaran kapal niaga global (baik *Voyage Charter* maupun *Time Charter*), biaya keterlambatan kapal di pelabuhan (*Demurrage*) dan bonus efisiensi pemuatan (*Despatch*) merupakan salah satu sumber sengketa komersial terbesar antara pemilik kapal (*Shipowner*) dan penyewa kargo (*Charterer*). 

Masalah utama yang dihadapi adalah:
1. **Perbedaan Interpretasi Statement of Facts (SOF):** Perselisihan mengenai jam-jam interupsi operasional (misalnya hujan deras pada kargo higroskopis seperti batubara atau biji-bijian, mogok buruh bongkar muat / *stevedore strike*, atau kerusakan peralatan derek kapal).
2. **Aturan Hari Libur (SHEX vs SHINC):** *Sundays and Holidays Excluded* (SHEX) mensyaratkan pengecualian akhir pekan dari perhitungan laytime, sedangkan *SHINC* menghitung waktu berjalan terus.
3. **Masa Tenggang Notice of Readiness (NOR):** Ketidaksepakatan apakah waktu tenggang (*turn-time* 6 jam) mulai dihitung saat tender NOR atau saat kapal tiba di jangkar labuh (*anchorage*).
4. **Prinsip Hukum Maritim Internasional:** Perlunya penegakan prinsip *"Once on demurrage, always on demurrage"* tanpa kompromi, di mana setelah laytime yang diizinkan habis, hari libur dan cuaca buruk tetap dihitung sebagai demurrage yang wajib dibayar.

---

## 2. Keputusan Desain & Arsitektur

Diputuskan untuk menerapkan standarisasi perhitungan komersial dan alur penyelesaian sengketa sewa kapal berbasis aturan **BIMCO Gencon 1994** dan yurisprudensi arbitrase maritim internasional:

1. **Mesin Kalkulasi Mandiri (Algoritma Bersertifikat A11):**
   - Laytime dievaluasi oleh kelas terverifikasi `Modules\Shared\Domain\Algorithms\Port\LaytimeDemurrageCalculator`.
   - Mengakumulasi durasi kotor dari waktu mulainya laytime (setelah masa tenggang NOR atau saat kerja dimulai lebih awal) hingga selesainya operasi kargo.
   - Memfilter interupsi SOF berdasarkan klausul kontrak:
     - Jika klausul *SHEX*, waktu hujan dan hari libur dipotong dari jam laytime.
     - Jika klausul *SHINC*, waktu akhir pekan tetap dihitung sebagai laytime terpakai.
     - Kerusakan peralatan kapal sendiri (*ship's fault*) SELALU dipotong dari waktu laytime terpakai terlepas dari klausul kontrak.
   - Setelah waktu laytime habis (*balance hours < 0*), sistem otomatis mengaktifkan status `DEMURRAGE` dan menerapkan prinsip *Once on Demurrage, Always on Demurrage*.
   - Menerapkan kalkulasi *Reversible Laytime* bila diperjanjikan (menggabungkan laytime muat dan bongkar).

2. **Penerbitan Otomatis Tagihan & Penanganan Status Sengketa:**
   - Bila hasil kalkulasi adalah `DEMURRAGE`, sistem otomatis menerbitkan faktur resmi `FleetDemurrageInvoice` dengan jatuh tempo pembayaran 14 hari kalender.
   - Bila penyewa mengajukan keberatan resmi, status kalkulasi ditandai sebagai `DISPUTED` dan status charter party beralih ke `DISPUTED`.
   - Sistem menyediakan klausul rujukan arbitrase standar maritim: *Singapore Chamber of Maritime Arbitration (SCMA)* atau *London Maritime Arbitrators Association (LMAA)*.

3. **Integritas Pembukuan Finansial (Double-Entry Ledger):**
   - Piutang demurrage dicatat pada akun debit `1200 (Accounts Receivable Demurrage)` dan kredit `4100 (Demurrage Revenue)`.
   - Penyelesaian damai atau putusan arbitrase disesuaikan melalui jurnal koreksi berpasangan dengan pencatatan nomor perkara sengketa.

---

## 3. Konsekuensi Arsitektural

- **Positif:** Mengeliminasi friksi interpretasi manual antara divisi operasional kapal, agensi pelabuhan, dan divisi komersial pemilik kapal. Setiap perhitungan memiliki audit trail matematis yang dapat dipertanggungjawabkan di hadapan arbiter maritim.
- **Batasan:** Format laporan *Statement of Facts* (SOF) harus memiliki stempel waktu ISO-8601 yang presisi dan dikonfirmasi bersama oleh nakhoda dan agen pelabuhan.
