# Panduan Operasional Pengukuran Skor Pengalaman Tamu (WPS & NPS) — Fase 117

## 1. Konsep Wait Per Service (WPS)
Skor WPS adalah indeks kepuasan waktu tunggu layanan antrean kasir bar, outlet F&B, dan loket merchandise pada venue:
- Waktu toleransi ideal pengunjung: $\le 3$ menit (WPS = 100).
- Waktu toleransi menengah: $3 - 7$ menit (WPS = 70 - 85).
- Waktu batas kritis komplain: $> 15$ menit (WPS = 0).

## 2. Rumus & Tindakan Korektif
$$\text{WPS Score} = \max\left(0, 100 - \left\lfloor \frac{\text{Waktu Tunggu Rata-rata (menit)} \times 100}{15} \right\rfloor \right)$$

Saat WPS Score berada di bawah 60 selama 2 siklus telemetri berturut-turut:
1. **Penerbitan Sinyal HCM Shift:** Sistem menerbitkan permintaan bala bantuan kasir via modul HCM.
2. **Promosi Buffer:** Pengunjung yang sedang mengantre ditawari air mineral gratis atau diskon minuman berikutnya untuk menjaga sentimen positif.
3. **Penyelarasan Brand Scorecard:** Nilai rata-rata WPS harian diagregasikan ke dalam skor kinerja brand venue (`venue_brand_scorecards`).

