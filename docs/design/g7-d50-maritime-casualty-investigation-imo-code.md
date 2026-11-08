# G7-D50: Maritime Casualty Investigation & IMO Casualty Investigation Code

## 1. Konteks Regulasi & Standar Keselamatan Pelayaran

Investigasi kecelakaan di laut (*Marine Casualty and Marine Incident Investigation*) diatur oleh *Code of the International Standards and Recommended Practices for a Safety Investigation into a Marine Casualty or Marine Incident* (IMO Casualty Investigation Code - Resolusi MSC.255(84)).

Tujuan utama investigasi menurut regulasi internasional bukanlah untuk menetapkan kesalahan (*liability or fault*), melainkan untuk mencegah keberulangan insiden serupa di masa depan dengan mengidentifikasi faktor penyebab teknis, prosedural, lingkungan, dan manusia (*Human Factors Analysis and Classification System - HFACS*).

Keputusan arsitektur **G7-D50** menetapkan integrasi pencatatan digital insiden maritim, investigasi multi-faktor, dan pelaporan statutori kepada Otoritas Maritim Bendera (*Flag State Administration*) serta IMO GISIS (*Global Integrated Shipping Information System*).

---

## 2. Klasifikasi Kategori Insiden Maritim

Berdasarkan keparahan dampak menurut IMO Casualty Code:

| Tingkat Keparahan | Definisi Regulasi IMO | Contoh Insiden | Tindakan Wajib Sistem |
|---|---|---|---|
| **Very Serious Casualty** | Insiden yang mengakibatkan hilangnya nyawa, kapal tenggelam/musnah total (*total loss*), atau pencemaran laut skala besar. | Tabrakan kapal berakibat karam, ledakan tangki muatan kargo, tumpahan minyak mentah > 100 ton. | Membekukan status pelayaran kapal, mengunci telemetri noon report dan VDR, memicu notifikasi darurat Direksi & Regulator. |
| **Serious Casualty** | Insiden yang tidak memenuhi kategori very serious, namun mengakibatkan kerusakan struktural parah, kebakaran, kandas, atau pencemaran. | Kerusakan mesin induk di alur sempit, kandas di karang tanpa kebocoran lambung, kebakaran di kamar mesin yang berhasil dipadamkan kru. | Mengalihkan status kapal ke `WEATHER_DEVIATION` atau `DISTRESS`, mewajibkan survei biro klasifikasi sebelum berlayar kembali. |
| **Less Serious Incident** | Insiden operasional ringan yang membahayakan namun tidak menyebabkan kerusakan parah. | Tali tambat putus saat proses sandar dermaga, kontak ringan dengan fender dermaga, senggolan perahu nelayan tanpa korban. | Pencatatan laporan insiden internal (*Near-Miss / Minor Casualty Report*), investigasi root cause dalam 14 hari kerja. |
| **Marine Incident** | Kejadian luar biasa yang berpotensi membahayakan operasi pelayaran (*Hazardous Occurrence*). | Kegagalan generator listrik cadangan saat manuver, kehilangan daya kemudi sementara selama < 5 menit. | Evaluasi Preventive Maintenance Schedule (PMS) dan inspeksi komponen kritis. |

---

## 3. Matriks Investigasi Human Factor (HFACS-Maritime)

Model investigasi mengadopsi taksonomi 4 tingkat *Human Factors Analysis and Classification System*:
1. **Unsafe Acts of Operators (Tindakan Tidak Aman)**:
   - *Errors*: Kesalahan navigasi berbasis persepsi penglihatan di malam hari, salah kalkulasi draft survey.
   - *Violations*: Sengaja mematikan sensor AIS untuk jalan pintas rute, mengabaikan aturan jam istirahat STCW.
2. **Preconditions for Unsafe Acts (Kondisi Awal)**:
   - *Environmental Factors*: Kabut tebal, arus laut silang di Selat Malaka, badai siklon tropis.
   - *Condition of Operators*: Kelelahan kru (*crew fatigue*), miskomunikasi lintas bahasa antara nahkoda dan pandu pelabuhan.
3. **Unsafe Supervision (Pengawasan Tidak Memadai)**:
   - Gagal mengaudit daftar kelengkapan check-list sebelum kapal berangkat.
   - Penugasan perwira tanpa pengalaman navigasi perairan dangkal.
4. **Organizational Influences (Kebijakan Organisasi)**:
   - Tekanan komersial dari pemilik kapal untuk memacu kecepatan kapal di atas batas aman (*commercial pressure vs safety culture*).

---

## 4. Alur Kerja Digital Pelaporan & Penyelidikan Insiden

```
+---------------------------------------------------------------+
|                       TERJADI INSIDEN MARITIM                 |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Langkah 1: Notifikasi Cepat & Pelestarian Bukti Digital       |
| - Bekukan log voyage, data AIS, sensor telemetri noon report   |
| - Terbitkan laporan awal (Flash Casualty Report) dalam 2 jam  |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Langkah 2: Pembentukan Tim Investigasi & Wawancara Kru       |
| - Penugasan Designated Person Ashore (DPA) & Marine Surveyor  |
| - Pengumpulan rekaman Voyage Data Recorder (VDR / Blackbox)   |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Langkah 3: Analisis Akar Masalah (Root Cause Analysis - RCA)  |
| - Klasifikasi faktor teknis, cuaca, dan HFACS-Maritime         |
| - Evaluasi kepatuhan ISM Code & Safety Management System (SMS)|
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Langkah 4: Rekomendasi Keselamatan & Laporan Statutori       |
| - Penerbitan Corrective and Preventive Actions (CAPA)         |
| - Pengiriman laporan formal ke Biro Klasifikasi & Flag State  |
+---------------------------------------------------------------+
```

---

## 5. Keputusan Teknis & Struktur Tabel Modul

Setiap investigasi kecelakaan maritim disimpan dalam tabel relasional `prt_fleet_casualty_investigations` yang terhubung langsung dengan ID kapal, nomor voyage, nomor asuransi, serta riwayat paspor aset append-only untuk memastikan jejak audit tidak dapat disangkal (*non-repudiation*).
