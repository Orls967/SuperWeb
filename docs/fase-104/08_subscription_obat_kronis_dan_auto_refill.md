# Langganan Obat Kronis, Prediksi Refill & Kepatuhan Terapi (Adherence Engine)

Dokumen ini menjelaskan rancangan subsistem pengelolaan penyakit kronis (Diabetes Melitus tipe 2, Hipertensi esensial, Dislipidemia, Penyakit Paru Obstruktif Kronik) pada modul Hospital Fase 104 melalui layanan langganan obat otomatis berulang (*Chronic Drug Subscription & Auto-Refill Engine*).

---

## 1. Tantangan Pasien Kronis & Rasional Bisnis

Pasien dengan penyakit tidak menular (PTM) membutuhkan pengobatan berkelanjutan seumur hidup. Berdasarkan data WHO, rata-rata kepatuhan pasien kronis di negara berkembang hanya berkisar 50%. Ketidakpatuhan memicu peningkatan risiko stroke, infark miokard, gagal ginjal, dan peningkatan beban biaya rawat inap yang sangat tinggi.

Subsistem ini mengatasi friksi tersebut dengan:
1. Menjadwalkan pengiriman obat rutin bulanan ke rumah pasien sebelum pasokan habis.
2. Memonitor indikator kepatuhan objektif (*Medication Possession Ratio*).
3. Mengotomatiskan penagihan berkala dan reservasi stok farmasi secara prediktif.

---

## 2. Struktur Model Langganan (`ChronicSubscription`)

Model `ChronicSubscription` menyimpan kontrak pengantaran berkala:
- `subscription_code`: Kode unik kontrak langganan (format `SUB-CHR-XXXXXXXX`).
- `patient_id`: Referensi ke pasien terdaftar.
- `pharmacy_branch_id`: Cabang apotek jaringan pelaksana peracikan dan pengiriman.
- `drug_code`: Kode sediaan obat (formularium RS).
- `monthly_quantity`: Jumlah dosis per 30 hari kalender.
- `unit_price_idr`: Harga per satuan tablet/kapsul/pen.
- `next_refill_date`: Tanggal target pelaksanaan batch refill berikutnya.
- `status`: `ACTIVE`, `PAUSED`, `CANCELLED`.

---

## 3. Eksekusi Batch Refill Otomatis

Perintah CLI `hsp:chronic-refill-cron` (`ChronicSubscriptionCronRefillCommand`) dieksekusi secara harian pada pukul 01:00 dini hari melalui scheduler aplikasi:

```
[Cron Job 01:00 WIB]
        │
        ▼
[Ambil Langganan ACTIVE dengan next_refill_date <= Today + 3 Hari]
        │
        ▼
[Untuk Setiap Langganan:]
        ├── 1. Validasi Keberadaan Stok Lot FEFO di Apotek Terpilih
        ├── 2. Buat Transaksi EPharmacyOrder (status: CONFIRMED)
        ├── 3. Bentuk Item Rincian di hsp_epharmacy_order_items
        ├── 4. Advance Jadwal: next_refill_date = next_refill_date + 30 Hari
        └── 5. Terbitkan Surat Jalan Pengiriman Kurir Logistik
```

Sifat idempoten dijamin: bila proses dijalankan ulang pada hari yang sama, langganan yang telah dimajukan tanggalnya tidak akan diproses ganda.

---

## 4. Evaluasi Kepatuhan Pasien (Medication Possession Ratio / MPR)

Sistem menghitung rasio kepemilikan obat (*Medication Possession Ratio* / MPR) selama periode evaluasi standar 90 hari:

$$MPR = \left( \frac{\text{Total Hari Pasokan Obat yang Ditebus}}{\text{Jumlah Hari Periode Observasi}} \right) \times 100\%$$

### Kategori Tingkat Kepatuhan (Adherence Tiers):
- **OPTIMAL** ($MPR \ge 80\%$): Pasien memiliki kepatuhan sangat baik; risiko komplikasi rendah.
- **SUBOPTIMAL** ($50\% \le MPR < 80\%$): Pasien rentan mengalami kesenjangan terapi; sistem mengirimkan notifikasi pengingat ramah via aplikasi/WhatsApp.
- **POOR** ($MPR < 50\%$): Kepatuhan buruk; sistem memicu *Clinical Outreach Flag* agar perawat edukator atau dokter keluarga menghubungi pasien secara langsung.

