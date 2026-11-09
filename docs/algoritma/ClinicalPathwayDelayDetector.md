# Algoritma: ClinicalPathwayDelayDetector (B3)

## 1. Intuisi & Latar Belakang
Clinical Pathway adalah rencana perawatan terstandarisasi multidisiplin untuk kelompok diagnosis tertentu. Setiap paket jalur klinis mendefinisikan serangkaian order medis, diagnostik laboratorium, radiologi, dan tindakan bedah yang memiliki dependensi urutan dan batas toleransi keterlambatan. Jika salah satu mata rantai tertunda, sistem harus membedakan antara "keterlambatan akar" (root-cause delay) yang memerlukan peringatan segera ke perawat/dokter, dan "keterlambatan turunan" (projected downstream delay) yang hanya memerlukan penyesuaian jadwal ulang tanpa membanjiri staf dengan spam alert berulang.

## 2. Aturan Perhitungan & Alert Tepat Sekali
1. Setiap order memiliki:
   - Waktu rencana awal (`planned_start_time` dalam menit sejak tengah malam)
   - Durasi estimasi (`duration_minutes`)
   - Order prasyarat (`prerequisite_order_ids`)
   - Waktu aktual mulai jika telah dieksekusi (`actual_start_time`)
2. Waktu Mulai Efektif ($T_{\text{effective}}$):
   $$T_{\text{effective}} = \max(\text{planned\_start}, \text{completion\_time}(\text{prerequisites}))$$
   Jika order memiliki waktu aktual mulai, gunakan nilai aktual tersebut.
3. Batas Toleransi Keterlambatan: 15 menit.
4. Kebijakan Notifikasi (Alert Invariant):
   - Alert dikirim **TEPAT SEKALI** per paket jalur klinis (pathway), yaitu untuk order pertama dalam rantai yang mengalami keterlambatan melebihi toleransi (> 15 menit).
   - Order turunan berikutnya yang terkena pergeseran jadwal hanya diproyeksikan ulang (re-projected) tanpa memicu alert baru selama keterlambatan tambahan relatif terhadap jadwal proyeksi barunya <= 15 menit.

## 3. Vektor Uji Resmi
- **Rantai Order (Menit sejak 00:00)**:
  - Order 1: `LAB` — Jadwal rencana 08:00 (menit 480), durasi 60 menit. Tanpa prasyarat.
  - Order 2: `RAD` — Jadwal rencana 09:00 (menit 540), durasi 45 menit. Prasyarat: `LAB`.
  - Order 3: `PROC` — Jadwal rencana 10:00 (menit 600), durasi 90 menit. Prasyarat: `RAD`.
- **Eksekusi Aktual**:
  - `LAB` baru dimulai pada pukul 08:25 (menit 505) -> Terlambat 25 menit.
- **Hasil Detektor**:
  - `LAB` mengalami keterlambatan 25 menit (> toleransi 15 menit).
  - Peringatan: Memicu **ALERT [LAB, 25 menit]**.
  - Proyeksi ulang order turunan:
    - Waktu selesai aktual `LAB` diproyeksikan: $505 + 60 = 565$ (pukul 09:25).
    - Proyeksi mulai `RAD`: $\max(540, 565) = 565$ (pukul 09:25).
    - Proyeksi selesai `RAD`: $565 + 45 = 610$ (pukul 10:10).
    - Proyeksi mulai `PROC`: $\max(600, 610) = 610$ (pukul 10:10).
    - Keterlambatan `RAD` adalah 25 menit yang diakibatkan oleh `LAB`.
    - Keterlambatan `PROC` relatif terhadap jadwal awal adalah 10 menit (<= toleransi 15 menit), sehingga **TIDAK ADA ALERT BARU**.

## 4. Sertifikat & Verifikasi
Hasil deteksi menghasilkan `ClinicalPathwayScheduleCertificate` yang memverifikasi kepatuhan topologis rantai DAG order dan bukti pengiriman alert tunggal melalui `PathwayScheduleCertificateVerifier`.

- Kompleksitas Waktu: $O(V + E)$ di mana $V$ adalah jumlah order klinis dan $E$ adalah dependensi urutan.
- Lokasi Kode: `Modules\Hospital\Domain\Algorithms\ClinicalPathwayDelayDetector.php`

