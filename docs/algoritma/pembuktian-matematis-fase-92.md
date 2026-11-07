# Dokumen Konsolidasi Pembuktian Matematis: Algoritma Fase 92

Dokumen ini mendokumentasikan landasan teori, analisis kompleksitas komputasi, dan pengujian invarian matematis untuk 4 algoritma bersertifikat pada modul Perhotelan Fase 92:
1. **H6: FolioSplitRouter**
2. **H7: LoyaltyNightTierEngine**
3. **H8: FractionalUsageScheduler**
4. **H9: ChurnRiskScorer**

---

## 1. Analisis Kompleksitas Komputasi

| Algoritma | Kompleksitas Waktu | Kompleksitas Ruang | Sifat Deterministik |
| :--- | :--- | :--- | :--- |
| **H6: FolioSplitRouter** | $\mathcal{O}(N)$ | $\mathcal{O}(N)$ | Deterministik penuh, urutan entri dipertahankan |
| **H7: LoyaltyNightTierEngine** | $\mathcal{O}(1)$ | $\mathcal{O}(1)$ | Evaluasi percabangan konstan $\mathcal{O}(1)$ |
| **H8: FractionalUsageScheduler** | $\mathcal{O}(K \log K)$ ($K \le 52$) | $\mathcal{O}(K)$ | Deterministik kalender ISO 8601 |
| **H9: ChurnRiskScorer** | $\mathcal{O}(1)$ | $\mathcal{O}(1)$ | Normalisasi skor kontinu terbobot |

---

## 2. Uji Vektor Nilai Batas (Boundary Test Vectors)

### 2.1 Vektor Uji H6 (Folio Split)
- **Kasus Uji A (Full Corporate)**:
  - Input: 3 item Room Charge IDR 1.000.000, Tax IDR 100.000, Service IDR 100.000. Limit IDR 5.000.000.
  - Expected: Corporate = IDR 1.200.000, Personal = 0, Konservasi selisih = 0.
- **Kasus Uji B (Batas Kredit Terlampaui)**:
  - Input: Room Charge IDR 2.000.000, Minibar IDR 500.000. Limit IDR 1.500.000.
  - Expected: Room Charge melebihi limit -> Corporate = 0, Personal = IDR 2.500.000.

### 2.2 Vektor Uji H7 (Loyalty Tier)
- $N = 9$ malam -> BASIC (Multiplier 1.00)
- $N = 10$ malam -> SILVER (Multiplier 1.15)
- $N = 25$ malam -> GOLD (Multiplier 1.30)
- $N = 50$ malam -> PLATINUM (Multiplier 1.50)

### 2.3 Vektor Uji H8 (Timeshare Schedule)
- Fraksi: 4 minggu. Permintaan pemilik: Minggu 26 (Peak Summer), Minggu 51 (Peak Year End).
- Sisa 2 minggu dialokasikan otomatis ke RENTAL_POOL tanpa tabrakan minggu.
- Total minggu terjadwal = 4 minggu persis.

### 2.4 Vektor Uji H9 (Churn Risk)
- Tamu tanpa keluhan, menginap 6 kali setahun, rating 5.0 -> $S_{churn} \le 0.15$ (LOW_RISK).
- Tamu tidak menginap 200 hari, 1 kali menginap, 2 keluhan terbuka -> $S_{churn} \ge 0.70$ (HIGH_RISK).

