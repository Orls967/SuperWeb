# Arsitektur Guest Experience AI & Personalization Loop — Fase 117

```mermaid
flowchart TD
    subgraph Penginderaan Titik Layanan
        T1[Sensor Antrean Stasiun Bar] --> WPS_ENG[WaitPerServiceGovernor V15]
        T2[POS Cashier Throughput Log] --> WPS_ENG
        T3[Deteksi Kehadiran Tamu VIP di Zona] --> PER_ENG[PersonalizedOfferContextRouter V16]
    end

    subgraph Optimasi Antrean Bar
        WPS_ENG --> WPS_METRIC[Kalkulasi Skor WPS 0-100 & Utilisasi Staf]
        WPS_METRIC --> CHECK_WPS{Skor WPS < 60?}
        CHECK_WPS -->|Ya| BOUNTY[Kirim Sinyal Shift Bounty ke Modul HCM]
        CHECK_WPS -->|Tidak| STEADY[Status Layanan Optimal]
    end

    subgraph Personalisasi On-Site VIP
        PER_ENG --> COOL[Cek Cooldown 1 Jam & Kuota 3 Promo/Malam]
        COOL -->|Lolos| MARGIN[Cek Batas Lantai Harga HPP/COGS]
        MARGIN -->|Lolos| DISPATCH[Kirim Penawaran Eksklusif ke App Pengunjung]
        COOL -->|Gagal| SUPPRESS[Tahan Penawaran - Anti-Fatigue]
        MARGIN -->|Gagal| FLOOR[Terapkan Harga Lantai COGS Minimum]
    end

    subgraph Feedback Loop Kepuasan
        DISPATCH --> REDEEM[Penukaran Promo di Kasir Bar]
        REDEEM --> NPS[Survei Kepuasan Cepat Pasca-Event]
        NPS --> SCORECARD[Agregasi ke Brand Scorecard Venue]
    end
```

## 1. Integrasi Nilai Bisnis & Pengalaman Pengunjung
- **Reduksi Waktu Tunggu Bar:** Dengan memantau laju layanan dan kedatangan menggunakan teori antrean M/M/c, lonjakan pesanan langsung diimbangi oleh pembukaan kasir cadangan sebelum pengunjung frustrasi.
- **Peningkatan Belanja Per Tamu (RevPAT):** Tamu VIP di zona premium menerima diskon khusus untuk produk bermargin tinggi secara personal tanpa menimbulkan perangkap diskon berlebihan berkat margin guardrail.
- **Kepatuhan Anti-Spam:** Batasan deterministik memastikan tamu merasa dihargai dengan penawaran relevan, bukan terganggu oleh banjir notifikasi.

