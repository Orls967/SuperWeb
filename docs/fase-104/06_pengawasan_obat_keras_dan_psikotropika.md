# Pengawasan Obat Keras Tertentu, Narkotika & Psikotropika (SIPNAP Compliance)

Dokumen ini menjelaskan rancangan arsitektur kepatuhan regulasi Kementerian Kesehatan dan Badan Pengawas Obat dan Makanan (BPOM) terkait peredaran obat-obatan golongan Narkotika, Psikotropika, dan Prekursor Farmasi melalui Sistem Pelaporan Narkotika dan Psikotropika (SIPNAP) pada modul Hospital Fase 104.

---

## 1. Landasan Hukum & Kebijakan Ketat

Berdasarkan UU No. 35 Tahun 2009 tentang Narkotika, UU No. 5 Tahun 1997 tentang Psikotropika, dan Permenkes No. 3 Tahun 2015:
- Telemedis **DILARANG** menyerahkan sediaan narkotika secara sembarangan tanpa otorisasi ganda.
- Setiap lembar resep yang mengandung golongan OPIOID, BENZODIAZEPINE, atau PRECURSOR wajib diverifikasi oleh minimal dua dokter berlisensi aktif (*dual-doctor sign-off*).
- Seluruh mutasi persediaan (penerimaan, pemakaian, peracikan, pemusnahan) wajib dicatat dalam kartu stok elektronik yang tidak dapat diubah (*immutable ledger*) dan dilaporkan bulanan ke SIPNAP Kemkes.

---

## 2. Mekanisme Dual-Doctor Sign-Off

Pada saat dokter pertama membuat pesanan resep narkotika/psikotropika melalui telemedis:
1. Status pesanan dikunci dengan kode `AWAITING_SECOND_DOCTOR_APPROVAL`.
2. Sistem menolak pembuatan surat jalan logistik kurir sampai dokter kedua memberikan otorisasi kriptografis melalui `ApproveControlledPrescriptionAction`.
3. Tanda tangan dokter kedua dibungkus dalam atribut `second_doctor_approval_hash`.
4. Jika persetujuan kedua tidak diberikan dalam waktu 24 jam, resep otomatis dibatalkan (`EXPIRED_PENDING_APPROVAL`).

```
[Dokter Telemedis Pertama] ────> Meresepkan Fentanyl / Morfin
            │
            ▼
[Status: AWAITING_SECOND_DOCTOR_APPROVAL]
            │
            ▼
[Notifikasi ke Kepala SMF / Dokter Konsulen Kedua]
            │
            ├── SETUJU: Input Kunci Otorisasi Kriptografis
            │     └── second_doctor_approval_hash dicatat
            │     └── Order dialihkan ke Apotek Khusus
            │
            └── TOLAK: Resep Dibatalkan & Alasan Tercatat di Audit Log
```

---

## 3. Quota Limiter & Deteksi Dokter-Shopper (Doctor Shopping Prevention)

Penyalahgunaan obat sering dilakukan dengan modus berpindah-pindah dokter (*doctor shopping*) untuk mengumpulkan kuota obat penenang melebihi batas terapi.

`NarcoticPrescriptionQuotaLimiter` menerapkan aturan restriksi berbasis Nomor Induk Kependudukan (NIK) dan Paspor Kesehatan Pasien:
- **Batas Maksimal Pasokan**: Maksimal 7 hari durasi terapi per resep rawat jalan / telemedis.
- **Interval Refill Minimum**: Pasien dilarang menebus resep baru untuk zat yang sama sebelum 80% dari durasi pasokan sebelumnya terlewati (misalnya pasokan 7 hari hanya boleh ditebus kembali pada hari ke-6 atau ke-7).
- **Audit Silang Jaringan**: Kuota dihitung secara terpusat di seluruh cabang jaringan rumah sakit, bukan hanya pada cabang lokal.

---

## 4. Format Laporan SIPNAP & Hash Integritas

Setiap transaksi dispensing narkotika menghasilkan entri serial pada `hsp_sipnap_transaction_logs`:
- Nomor Batch / Lot Pabrik.
- Nama dan Nomor SIP Dokter Penulis Resep.
- Nama Pasien, NIK, dan Alamat Lengkap.
- Kuantitas yang Diserahkan (dalam satuan terkecil: ampul, tablet, botol).
- Tanggal dan Jam Penyerahan.
- Identitas Petugas Farmasi yang Melakukan Verifikasi Akhir.

Di akhir siklus pelaporan bulanan, `SipnapRegulatoryReportingEngine` mengompilasi berkas laporan resmi dan menghitung hash SHA-256 integritas rantai untuk menjamin tidak ada manipulasi data masa lalu (*zero tampering*).

