# Clinical Decision Support Engine (CDSE) & Matriks Interaksi Farmakologis

Dokumen ini menjelaskan rancangan, formula matematis, serta mekanisme verifikasi keselamatan farmakoterapi pada modul Hospital Fase 104 dalam kerangka arsitektur SuperWeb E-Health Ecosystem.

---

## 1. Latar Belakang & Standar Keselamatan Pasien

Medication error merupakan salah satu penyebab utama adverse drug events (ADE) yang dapat dicegah di fasilitas pelayanan kesehatan. Berdasarkan standar akreditasi KARS dan JCI (Joint Commission International), setiap peresepan elektronik wajib melalui filter keamanan bertingkat sebelum obat dapat diproses dan diserahkan kepada pasien (*dispensing*).

Sistem Telemedicine & E-Pharmacy mengimplementasikan Clinical Decision Support Rule Engine (`ClinicalDecisionSupportRuleEngine`) yang beroperasi secara deterministik pada titik peresepan (`IssueElectronicPrescriptionAction`) dan evaluasi pra-dispensing.

---

## 2. Taksonomi Pemeriksaan Farmakologis

CDSE menjalankan 5 pilar evaluasi independen:

```
[Resep Dokter Masuk]
        │
        ├── 1. Penapisan Alergi Silang (Cross-Allergy Matrix)
        │       ├── Riwayat alergi langsung pasien (AllergyMatrix)
        │       └── Pemetaan golongan homolog (Penicillin <-> Amoxicillin)
        │
        ├── 2. Penyesuaian Klirens Ginjal & Hepar
        │       ├── Formula Cockcroft-Gault (CrCl) & MDRD
        │       └── Skor Child-Pugh untuk sirosis hepar
        │
        ├── 3. Matriks Interaksi Antar-Obat (Drug-Drug Interactions / DDI)
        │       ├── Severity: CONTRAINDICATED, MAJOR, MODERATE, MINOR
        │       └── Kombinatorial pair scan O(N^2)
        │
        ├── 4. Batas Dosis Maksimal Harian & Rentang Terapi Sempit
        │       ├── Perhitungan total course dose & daily dose ratio
        │       └── Deteksi polifarmasi berbahaya
        │
        └── 5. High-Alert & LASA (Look-Alike Sound-Alike / NORUM)
                ├── Tall-Man Lettering standar ISMP
                └── Kewajiban Independent Double-Check
```

---

## 3. Formula Klirens Ginjal (Cockcroft-Gault)

Laju filtrasi glomerulus diestimasi melalui Klirens Kreatinin (Creatinine Clearance / CrCl) dengan rumus:

$$CrCl = \frac{(140 - \text{Umur}) \times \text{Berat Badan (kg)}}{72 \times \text{Serum Kreatinin (mg/dL)}} \times [0.85 \text{ bila wanita}]$$

Pada pasien dengan obesitas (berat aktual > 120% Ideal Body Weight), sistem menggunakan *Adjusted Body Weight* (ABW):

$$IBW_{\text{pria}} = 50.0 + 2.3 \times (\text{Tinggi (inci)} - 60)$$
$$IBW_{\text{wanita}} = 45.5 + 2.3 \times (\text{Tinggi (inci)} - 60)$$
$$ABW = IBW + 0.4 \times (\text{Berat Aktual} - IBW)$$

### Klasifikasi Penyakit Ginjal Kronis (CKD Stages):
1. **Stage 1 (Normal / Tinggi)**: $CrCl \ge 90\text{ mL/min}$ — Dosis 100% penuh.
2. **Stage 2 (Penurunan Ringan)**: $60 \le CrCl < 90\text{ mL/min}$ — Penyesuaian interval bila nefrotoksik.
3. **Stage 3 (Penurunan Sedang)**: $30 \le CrCl < 60\text{ mL/min}$ — Reduksi dosis 25–50% (misal Metformin maks 1000 mg).
4. **Stage 4 (Penurunan Berat)**: $15 \le CrCl < 30\text{ mL/min}$ — Kontraindikasi mutlak Metformin, perpanjang interval Gentamicin ke 48 jam.
5. **Stage 5 (Gagal Ginjal Terminal)**: $CrCl < 15\text{ mL/min}$ — Khusus dialisis, reduksi > 75%.

---

## 4. Matriks Interaksi Antar-Obat (DDI)

Katalog interaksi mencakup interaksi farmakokinetik (inhibisi/induksi isoenzim sitokrom P450 CYP3A4, CYP2C19, CYP1A2) serta farmakodinamik (sinergisme depresi SSP, pemanjangan interval QTc):

| Pasangan Obat | Derajat Keparahan | Mekanisme Farmakologi | Mitigasi Klinis |
|---|---|---|---|
| **WARFARIN + ASPIRIN** | MAJOR | Penghambatan agregasi platelet ganda + antikoagulan melipatgandakan risiko perdarahan GI. | Hindari kombinasi kecuali pada katup mekanik; pantau serial INR. |
| **WARFARIN + CIPROFLOXACIN** | MAJOR | Inhibisi CYP1A2 & penekanan flora usus sintesis vit K meningkatkan INR secara liar. | Reduksi dosis Warfarin 30–50%; cek INR tiap 48 jam. |
| **SIMVASTATIN + AMLODIPINE** | MODERATE | Amlodipine menghambat CYP3A4 meningkatkan AUC Simvastatin hingga 77% (risiko rhabdomyolysis). | Batasi dosis Simvastatin maksimal 20 mg/hari. |
| **CLOPIDOGREL + OMEPRAZOLE** | MAJOR | Omeprazole menghambat bioaktivasi prodrug Clopidogrel pada CYP2C19. | Substitusi dengan Pantoprazole. |
| **METHOTREXATE + NSAID** | CONTRAINDICATED | Penurunan klirens renal MTX memicu toksisitas sumsum tulang akut fatal. | Blokir resep secara otomatis di sistem. |

---

## 5. Algoritma High-Alert & Tall-Man Lettering

Obat Look-Alike Sound-Alike (LASA) rentan tertukar saat pembacaan visual resep. Sistem menerapkan konvensi *Tall-Man Lettering* standar ISMP (Institute for Safe Medication Practices):
- `hydrALAZINE` vs `hydrOXYzine`
- `predniSONE` vs `prednisoLONE`
- `vinBLAStine` vs `vinCRIStine`
- `ePHEDrine` vs `EPINEPHRINE`

Setiap order yang mengandung obat High-Alert atau LASA secara otomatis mewajibkan status `requires_independent_double_check = true` dan mengunci penerbitan surat jalan farmasi sampai diverifikasi oleh apoteker kedua.

