# Buku Panduan SLO & Error Budget Platform (Fase 237)

## 1. Konsep Dasar Keandalan SRE (Google SRE Framework)
Dalam rekayasa keandalan sistem modern (Site Reliability Engineering), uptime 100% adalah target yang salah dan kontra-produktif: biaya pencapaiannya eksponensial dan menghambat laju inovasi pengembang. Sebagai gantinya, sistem menerapkan:
- **SLI (Service Level Indicator)**: Ukuran kuantitatif kepatuhan layanan aktual (misal rasio permintaan berhasil terhadap total permintaan).
- **SLO (Service Level Objective)**: Target keandalan formal yang disepakati (misal 99.90% per bulan kalender).
- **Error Budget (Anggaran Kegagalan)**: Batas toleransi kegagalan yang diizinkan:
$$\text{Error Budget} = 100\% - \text{SLO}$$

Jika suatu pilar memiliki target SLO 99.90%, maka error budget bulanan adalah 0.10%, setara dengan:
$$30 \text{ hari} \times 24 \text{ jam} \times 60 \text{ menit} \times 0.001 = 43.2 \text{ menit downtime / bulan}$$

---

## 2. Tingkatan Layanan (SLO Tiers)

Modul `Platform` menetapkan 3 tingkatan SLO (`SloTier`):

| Tingkatan | Kode Tier | Target SLI (Basis Poin) | Toleransi Downtime Bulanan | Karakteristik Layanan |
|---|---|---|---|---|
| **Tier 1 (Kritis)** | `tier_1_critical` | 99.99% (9999 bps) | 4.32 menit | Core Ledger, Banking Posting, Payment Gateway, Flight Dispatch Gate |
| **Tier 2 (Tinggi)** | `tier_2_high` | 99.90% (9990 bps) | 43.2 menit | Order Processing, Inventory Reservation, Hospital Admission, Bed Allocation |
| **Tier 3 (Standar)** | `tier_3_standard` | 99.00% (9900 bps) | 432 menit (7.2 jam) | Pelaporan CSV, Dashboard Analitik, Dev Portal, Batch Archiving |

---

## 3. Rumus Multi-Window Burn Rate & Kebijakan Alert

Burn rate ($B$) mengukur seberapa cepat error budget terkonsumsi dibandingkan dengan laju ideal konsumsi selama 30 hari. Burn rate $1.0\times$ berarti budget akan habis tepat pada akhir hari ke-30.

$$B = \frac{\text{Laju Kesalahan Aktual (Error Rate)}}{\text{Toleransi Kesalahan yang Diizinkan (Allowed Error Rate)}}$$

### Matriks Peringatan SRE Berjenjang (Google SRE Standard):

```
+------------+-------------+---------------------+-------------------+---------------------+
| Alert Tier | Jendela Jam | Ambang Burn Rate    | Budget Terkonsumsi| Saluran Notifikasi  |
+------------+-------------+---------------------+-------------------+---------------------+
| Critical   | 1 Jam       | >= 14.4x            | 2.0% dalam 1 jam  | PagerDuty (On-Call) |
| Major      | 6 Jam       | >= 6.0x             | 5.0% dalam 6 jam  | Slack Incident Room |
| Warning    | 24 Jam      | >= 1.0x             | 3.3% dalam 24 jam | Email Daily Digest  |
| Healthy    | -           | < 1.0x              | Normal            | -                   |
+------------+-------------+---------------------+-------------------+---------------------+
```

Peringatan kritis ($14.4\times$) segera memicu eskalasi on-call engineer karena jika dibiarkan tanpa penanganan, 100% anggaran keandalan bulanan akan habis dalam kurun waktu 50 jam.

---

## 4. Kebijakan Pembekuan Rilis (Deployment Freeze)

Ketika Error Budget suatu pilar terkuras hingga $\le 0\%$, subsistem `Platform` secara otomatis memberlakukan **Error Budget Policy**:
1. Seluruh rilis fitur non-kritis untuk pilar tersebut dibekukan (Release Freeze).
2. Prioritas sprint tim dialihkan 100% ke investigasi stabilitas, perbaikan bug, dan optimasi performa.
3. Rilis hanya diizinkan untuk emergency hotfix yang telah disetujui melalui CAB (Change Advisory Board) sesuai alur Fase 238.
4. Rilis fitur baru dibuka kembali setelah siklus kalender baru atau setelah sistem membuktikan stabilitas SLI selama minimal 7 hari berturut-turut.

---

## 5. Postmortem & Remediasi Insiden

Setiap insiden yang menyebabkan penipisan error budget signifikan wajib mendokumentasikan postmortem pada tabel `plt_postmortems`:
- `incident_ref`: Kode unik insiden (mis. `INC-20261109-001`).
- `severity`: Tingkat keparahan (`sev1_critical`, `sev2_major`, `sev3_minor`).
- `root_cause`: Analisis akar masalah 5-Whys.
- `action_items`: Daftar tindakan preventif terukur dengan penanggung jawab dan batas waktu penyelesaian.

