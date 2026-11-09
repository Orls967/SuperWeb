# Service Level Objectives (SLO) & Pengelolaan Error Budget Rilis

Dokumen ini menjelaskan spesifikasi Service Level Objectives (SLO), Service Level Indicators (SLI), dan kebijakan pengelolaan anggaran kesalahan (Error Budget) pada modul Platform.

---

## 1. Arsitektur SLO / SLI pada Ekosistem Platform

Keandalan sistem diukur berdasarkan pengalaman pengguna nyata melalui indikator terukur:
- **Service Level Indicator (SLI)**: Ukuran kuantitatif layanan yang diberikan secara langsung (misal: rasio kueri sukses atau latensi respons di bawah 300ms).
- **Service Level Objective (SLO)**: Target keandalan yang disepakati oleh tim rekayasa dan pemangku kepentingan bisnis dalam periode tertentu (misal: 99.9% kueri sukses dalam jendela 30 hari).
- **Error Budget (Anggaran Kesalahan)**: Batas toleransi ketidakandalan yang diizinkan sebelum sistem dianggap melanggar perjanjian tingkat layanan ($100\% - \text{SLO}$).

---

## 2. Definisi SLI dan Target SLO Kunci

Modul Platform menetapkan target keandalan untuk operasi rilis dan runtime aplikasi:

| Indikator Layanan (SLI) | Definisi Pengukuran | Target SLO (30 Hari) | Anggaran Error |
|---|---|---|---|
| **Ketersediaan API (Availability)** | Rasio respons non-5xx terhadap seluruh permintaan API | 99.95% | 0.05% (~21.6 menit downtime) |
| **Latensi Respons (Latency)** | Persentase permintaan diselesaikan dalam < 300ms | 99.00% | 1.00% kueri lambat |
| **Keberhasilan Pipeline Rilis** | Rasio penerapan tanpa insiden rollback atau hotfix darurat | 98.00% | 2.00% kegagalan penerapan |
| **Integritas Migrasi Skema** | Persentase migrasi skema yang selesai tanpa table lock > 5 detik | 99.99% | 0.01% kunci lambat |

---

## 3. Matematika Multi-Window Multi-Burn-Rate Alerts

Untuk mendeteksi konsumsi anggaran kesalahan yang terlalu cepat selama masa observasi canary, sistem menerapkan formula Google SRE Multi-Burn-Rate:

$$\text{Burn Rate} = \frac{\text{Observed Error Rate}}{1.0 - \text{SLO}}$$

Ambang batas peringatan didefinisikan sebagai berikut:

| Jendela Evaluasi | Burn Rate Multiplier | Konsumsi Budget | Tindakan Sistem |
|---|---|---|---|
| **1 Jam** | 14.4x | 2% anggaran habis dalam 1 jam | Peringatan Kritis (Paging), Auto-Rollback Canary Seketika |
| **6 Jam** | 6.0x | 5% anggaran habis dalam 6 jam | Peringatan Darurat, Hentikan Kenaikan Traffic Split Canary |
| **24 Jam** | 3.0x | 10% anggaran habis dalam 24 jam | Tiket Investigasi Prioritas Tinggi ke Tech Lead |
| **3 Hari** | 1.0x | Konsumsi normal berkelanjutan | Pencatatan Metrik Rutin |

Deteksi ini dijalankan secara otonom oleh komponen `ErrorBudgetDepletionAlertEngine` dan `SloBurnRateImpactPredictor`.

---

## 4. Kebijakan Pembekuan Rilis Saat Error Budget Habis

Jika anggaran kesalahan untuk jendela berjalan telah habis terpakai (Error Budget = 0%):
1. **Kebijakan Pembekuan Otomatis (Policy Freeze)**:
   - Seluruh rilis fitur baru untuk modul terkait ditangguhkan secara otomatis.
   - Sistem rilis hanya mengizinkan penerapan Change Request yang bertujuan untuk perbaikan keandalan sistem, pengoptimalan performa, atau penutupan kerentanan keamanan.
2. **Pemulihan Anggaran (Budget Replenishment)**:
   - Kebijakan pembekuan dicabut secara otomatis ketika sistem berjalan stabil dan anggaran kesalahan kembali tersedia di atas ambang batas 20%.

---

## 5. Pelaporan Telemetri dan Dasbor Keandalan

Metrik SLO diperbarui secara berkala dan dapat dipantau melalui dasbor web di `/platform/slos` atau melalui perintah konsol CLI:
```bash
php artisan platform:slo-status --format=summary
```
Integrasi ini memastikan bahwa kecepatan inovasi fitur baru selalu seimbang dengan komitmen keandalan operasional sistem.

