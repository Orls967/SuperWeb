# Protokol Penanganan Insiden, Resiliensi & Mitigasi Kegagalan (Disaster Recovery)

Dokumen ini mendefinisikan Standard Operating Procedure (SOP) penanganan insiden teknologi informasi, kegagalan rantai pasok farmasi, serta protokol kedaruratan klinis pada modul Hospital Fase 104.

---

## 1. Klasifikasi Insiden & Tingkat Keparahan (Severity Levels)

| Level Insiden | Definisi & Skenario | Target Response Time (MTTR) | Penanggung Jawab |
|---|---|---|---|
| **SEV-1 (Critical)** | Kegagalan total sistem telemedis, basis data terkunci, pelanggaran keamanan data medis berskala luas, atau penyerahan obat tertukar/kedaluwarsa fatal. | $\le 15\text{ menit}$ respon, $\le 60\text{ menit}$ resolusi | Tim Incident Commander, Direktur Medis, Lead Engineer |
| **SEV-2 (Major)** | Kegagalan bridging SISRUTE/BPJS, kegagalan batch auto-refill obat kronis, atau pemadaman komunikasi dengan cabang apotek tertentu. | $\le 30\text{ menit}$ respon, $\le 2\text{ jam}$ resolusi | Tim Backend, Lead DevOps, Apoteker Penanggung Jawab |
| **SEV-3 (Minor)** | Sensor telemetri boks kurir mengalami *intermittent delay*, galat minor tampilan antarmuka, notifikasi SMS pengingat tertunda. | $\le 2\text{ jam}$ respon, $\le 24\text{ jam}$ resolusi | Engineer On-Call, Support Helpdesk |

---

## 2. Protokol Kegagalan Rantai Dingin Kurir (Thermal Excursion Protocol)

Ketika sensor suhu mendeteksi lonjakan temperatur di atas $8.0^\circ\text{C}$ selama lebih dari 5 menit:
1. **Karantina Otomatis di Sistem**:
   `ColdChainMedicineDeliveryTracker` mengubah status pengiriman menjadi `QUARANTINED_BREACH` dan membekukan opsi konfirmasi e-POD.
2. **Peringatan Suara & Visual pada Aplikasi Kurir**:
   Aplikasi kurir menampilkan peringatan merah pekat: *"PERINGATAN: Suhu boks pendingin melebihi ambang batas aman. DILARANG menyerahkan obat ke pasien!"*.
3. **Instruksi Pengembalian Barang**:
   Kurir diinstruksikan menghentikan rute dan segera membawa paket kembali ke Instalasi Farmasi asal untuk investigasi formal.
4. **Penerbitan Paket Pengganti Prioritas (Stat Replacement)**:
   Sistem secara otomatis membuat pesanan pengganti tanpa biaya tambahan (*zero-charge re-order*) dari lot apotek terdekat lainnya dan menugaskan kurir prioritas baru guna meminimalkan keterlambatan terapi pasien.
5. **Pencatatan Berita Acara Kerusakan (Spoilage Report)**:
   Apoteker penanggung jawab mencatat lot terkait sebagai barang rusak (*write-off* persediaan) dan melampirkan log grafik suhu sensor IoT sebagai bukti klaim asuransi logistik.

---

## 3. Protokol Pemadaman Jaringan SISRUTE / Bridging BPJS (Offline Fallback)

Bila koneksi internet atau server pusat SISRUTE Kemenkes mengalami *downtime*:
1. **Antrean Tertunda (Asynchronous Outbox Pattern)**:
   Permohonan rujukan disimpan secara lokal dalam status `PENDING_RETRY` pada tabel `hsp_bpjs_referral_bridges`.
2. **Exponential Backoff**:
   Pekerja latar belakang (background queue worker) mencoba mengirimkan kembali muatan setiap 2, 4, 8, hingga 30 menit.
3. **Eskalasi Manual Melalui Jalur Khusus**:
   Jika server rujukan tidak merespon dalam waktu 15 menit untuk pasien kategori `EMERGENCY`:
   - Sistem memicu SMS / WhatsApp darurat ke nomor hotline Unit Gawat Darurat (UGD) rumah sakit tujuan.
   - Petugas administrasi telemedis menghubungi kontak telepon resmi RS rujukan untuk reservasi manual tempat tidur.
   - Pasien segera diberangkatkan dengan ambulans terdekat dengan membawa surat rujukan cetak fisik berstempel QR-code sementara.

---

## 4. Protokol Pemulihan Bencana Basis Data (Database Disaster Recovery)

1. **Replikasi Streaming Multi-AZ**: Basis data beroperasi dengan replikasi aktif-pasif lintas zona ketersediaan data center.
2. **Point-In-Time Recovery (PITR)**: Log transaksi write-ahead diarsipkan setiap 5 menit dengan retensi 30 hari.
3. **Verifikasi Integritas Dua Sumber (Two-Source Integrity Audit)**:
   Perintah `php artisan hosp:audit` dijalankan otomatis setiap jam untuk membuktikan tidak ada data resep atau mutasi saldo yang korup atau hilang.
