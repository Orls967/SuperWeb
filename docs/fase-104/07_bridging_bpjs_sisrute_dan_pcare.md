# Integrasi Bridging BPJS Kesehatan, SISRUTE & PCare Telemedis

Dokumen ini mendokumentasikan spesifikasi integrasi antarsistem antara platform Hospital Fase 104 dengan sistem eksternal jaminan kesehatan nasional BPJS Kesehatan, Sistem Rujukan Terintegrasi (SISRUTE), dan Primary Care (PCare).

---

## 1. Arsitektur Bridging & Standar Protokol

Integrasi interoperabilitas layanan kesehatan di Indonesia mengacu pada standar interoperabilitas Kementerian Kesehatan dan BPJS Kesehatan:
- **Protokol Komunikasi**: HTTPS REST API dengan enkripsi muatan AES-256-CBC dan kompresi LZ-String.
- **Header Keamanan**:
  - `X-Cons-ID`: Consumer ID terdaftar di BPJS TrustMark.
  - `X-Timestamp`: Unix Epoch time dalam detik.
  - `X-Signature`: HMAC-SHA256 dari `ConsID + "&" + Timestamp` dengan `SecretKey`.
  - `user_key`: Kunci akses layanan spesifik vClaim / PCare.

---

## 2. SISRUTE (Sistem Rujukan Terintegrasi Kemenkes)

Ketika dokter telekonsultasi mendeteksi perburukan kondisi klinis pasien (*clinical deterioration*) yang memerlukan penanganan darurat atau rawat inap lanjutan, sistem mengeksekusi `BridgeSisruteReferralAction`.

### Komponen Rujukan SISRUTE:
1. **Resume Medis Elektronik Rujukan**:
   - Riwayat anamnesis dan keluhan utama telekonsultasi.
   - Hasil pemeriksaan tanda vital yang diinput pasien atau wearable IoT.
   - Diagnosis kerja berbasis kode ICD-10 (misal `I21.9` untuk sindrom koroner akut).
   - Alasan rujukan: Ketiadaan fasilitas diagnostik lanjutan, kebutuhan ruang ICU/HCU, atau konsultasi dokter subspesialis.
2. **Kategori Kegawatan Rujukan**:
   - `EMERGENCY`: Memerlukan ambulans gawat darurat dan respon fasilitas penerima < 15 menit.
   - `URGENT`: Rujukan terjadwal < 24 jam dengan reservasi tempat tidur otomatis.
   - `ROUTINE`: Rujukan elektif rawat jalan ke poliklinik subspesialis.

Model `BpjsReferralBridge` mencatat status rujukan:
- `PENDING`: Menunggu respon RS rujukan tujuan.
- `ACCEPTED`: Diterima oleh RS tujuan, bed rawat inap berhasil dipesan.
- `REJECTED`: Ditolak (disertai alasan kapasitas penuh atau alat rusak).
- `TRANSFERRED`: Pasien telah tiba di instalasi gawat darurat penerima.

---

## 3. PCare & E-Resep BPJS (Klaim Kapitasi & Non-Kapitasi)

Pada fasilitas kesehatan tingkat pertama (FKTP) dan poliklinik telemedis jejaring BPJS:
1. Validasi kepesertaan aktif peserta BPJS secara realtime sebelum sesi telekonsultasi dimulai.
2. Pembuatan nomor rujukan internal untuk pasien Program Pengelolaan Penyakit Kronis (Prolanis).
3. Pengiriman berkas e-resep BPJS langsung ke apotek PRB (Program Rujuk Balik) terdekat untuk dispensing obat 30 hari tanpa membebani pasien dengan biaya tambahan (*zero out-of-pocket payment*).

