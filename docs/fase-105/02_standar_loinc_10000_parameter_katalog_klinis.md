# Standar LOINC 10.000 Parameter Katalog Klinis (Fase 105)

## 1. Definisi & Struktur Standar LOINC

LOINC (*Logical Observation Identifiers Names and Codes*) adalah standar interoperabilitas internasional yang dikembangkan oleh Regenstrief Institute untuk mengidentifikasi observasi laboratorium medis dan hasil pemeriksaan diagnostik secara universal.

Dalam arsitektur Hospital Fase 105, katalog laboratorium dimodelkan dengan struktur relasional 6-sumbu kanonikal LOINC yang mencakup:

| Sumbu LOINC | Definisi Medis | Contoh Penerapan |
|---|---|---|
| **Component / Analyte** | Zat, molekul, sel, atau mikroorganisme yang diukur | Glukosa, Hemoglobin, Kalium, Troponin I |
| **Property** | Karakteristik fisis/kimiawi yang diobservasi | Konsentrasi massa (`MCnc`), Konsentrasi substansi (`SCnc`), Fraksi volume (`VFr`) |
| **Time Aspect** | Interval waktu saat observasi dilakukan | Titik waktu sesaat (`Pt`), Akumulasi 24 jam (`24H`) |
| **System / Specimen** | Matriks atau jenis spesimen biologis | Darah lengkap (`Bld`), Serum/Plasma (`Ser/Plas`), Urin (`Urn`), Cairan Serebrospinal (`CSF`) |
| **Scale Type** | Skala pengukuran hasil | Kuantitatif (`Qn`), Kualitatif/Nominal (`Nom`), Ordinal (`Ord`) |
| **Method Type** | Metode analitik spesifik (bila mempengaruhi interpretasi) | Spektrofotometri, Elektroforesis, ELISA, Real-Time PCR, Flow Cytometry |

```mermaid
flowchart TD
    subgraph LOINCStructure["6 Sumbu Kanonikal LOINC"]
        AX1["1. Component (Zat)"]
        AX2["2. Property (Karakteristik)"]
        AX3["3. Time Aspect (Waktu)"]
        AX4["4. System (Spesimen)"]
        AX5["5. Scale (Skala Ukur)"]
        AX6["6. Method (Metode Uji)"]
    end

    AX1 & AX2 & AX3 & AX4 & AX5 & AX6 --> CODE["LOINC Identifier: e.g. 2823-3 (Potassium [Moles/volume] in Serum or Plasma)"]
    CODE --> DB[(Tabel hsp_lab_catalogs)]
    DB --> SCH[Mesin Pencarian Multidimensi & Pencocokan Otomatis]
```

## 2. Struktur Data Tabel `hsp_lab_catalogs`

Tabel `hsp_lab_catalogs` menyimpan kamus parameter pengujian laboratorium dengan definisi kolom teknis sebagai berikut:

```sql
CREATE TABLE hsp_lab_catalogs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loinc_num VARCHAR(32) NOT NULL UNIQUE,
    component VARCHAR(128) NOT NULL,
    property VARCHAR(32) NOT NULL,
    time_aspect VARCHAR(16) NOT NULL DEFAULT 'Pt',
    system_specimen VARCHAR(64) NOT NULL,
    scale_type VARCHAR(16) NOT NULL DEFAULT 'Qn',
    method_type VARCHAR(64) NULL,
    class_name VARCHAR(64) NOT NULL, -- CHEM, HEM/BC, COAG, MICRO, SERO, TOX
    reference_min DECIMAL(12, 4) NULL,
    reference_max DECIMAL(12, 4) NULL,
    unit VARCHAR(32) NULL,
    panic_low DECIMAL(12, 4) NULL,
    panic_high DECIMAL(12, 4) NULL,
    price_idr BIGINT NOT NULL DEFAULT 0,
    is_orderable BOOLEAN NOT NULL DEFAULT TRUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_loinc_class_order (class_name, is_orderable),
    INDEX idx_loinc_component (component)
);
```

## 3. Matriks Panel Pemeriksaan Klinis Utama

Hospital Fase 105 mengelompokkan 10.000 parameter pengujian ke dalam paket/panel klinis standar:

```mermaid
flowchart LR
    PANEL["Panel Pemeriksaan Klinis"]
    
    PANEL --> CBC["Darah Rutin Lengkap (CBC)"]
    CBC --> CBC_WBC["26464-8: Leukosit (WBC)"]
    CBC --> CBC_RBC["789-8: Eritrosit (RBC)"]
    CBC --> CBC_HGB["718-7: Hemoglobin (HGB)"]
    CBC --> CBC_PLT["777-3: Trombosit (PLT)"]

    PANEL --> CMP["Comprehensive Metabolic Panel (CMP)"]
    CMP --> CMP_GLU["2345-7: Glukosa Sewaktu"]
    CMP --> CMP_BUN["3094-0: Ureum (BUN)"]
    CMP --> CMP_CRE["2160-0: Kreatinin Serum"]
    CMP --> CMP_K["2823-3: Kalium (K+)"]
    CMP --> CMP_NA["2951-2: Natrium (Na+)"]

    PANEL --> CARD["Panel Biomarker Jantung"]
    CARD --> CARD_TROP["10839-9: Troponin I"]
    CARD --> CARD_CKMB["13969-1: CK-MB Massa"]
    CARD --> CARD_BNP["33762-6: NT-proBNP"]

    PANEL --> HEPAT["Panel Fungsi Hati (LFT)"]
    HEPAT --> HEP_SGOT["1920-8: AST / SGOT"]
    HEPAT --> HEP_SGPT["1742-6: ALT / SGPT"]
    HEPAT --> HEP_TBIL["1975-2: Total Bilirubin"]
```

## 4. Algoritma Pencarian & Pencocokan Otomatis LOINC

Untuk memudahkan tenaga medis dan integrasi sistem eksternal, `LoincCatalogSearchService` menyediakan algoritma pencocokan multi-kriteria:
1. **Exact Code Match**: Pencarian langsung berbasis `loinc_num` (kompleksitas $O(1)$).
2. **Normalized Prefix & Token Match**: Pemecahan kata kunci pencarian menjadi token leksikal (misal: "kalium darah" atau "potassium serum") dicocokkan terhadap `component` dan `system_specimen`.
3. **Category Filtering**: Penyaringan instan berdasarkan kelas uji medis (`CHEM`, `HEM`, `COAG`, `MICRO`).
4. **Orderable Guard**: Hanya parameter bertanda `is_orderable = true` yang dapat dimasukkan ke dalam keranjang pesanan dokter.

