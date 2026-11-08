# ADR-0052: Perhitungan Konsumsi Bahan Bakar dan Pelaporan Emisi ESG (G7-D52)

## Status
Diterima (Accepted) — 2026-11-08

## Konteks
Sektor transportasi udara dan pelayaran laut merupakan kontributor signifikan emisi gas rumah kaca global. Kepatuhan terhadap pedoman IMO (MEPC.308(73), MEPC.354(78)) dan kalkulasi emisi Scope 1 grup membutuhkan pencatatan konsumsi bahan bakar yang presisi dan pelaporan terintegrasi ke modul ESG.

## Keputusan
1. **Pencatatan Konsumsi per Leg:**
   - Penerbangan: konsumsi bahan bakar avtur (*fuel burn*) dicatat per sektor penerbangan dalam satuan kilogram (kg integer).
   - Kapal laut: konsumsi bahan bakar (HFO, VLSFO, MGO) dicatat per leg pelayaran dalam ton metrik.
2. **Faktor Emisi Berversi:**
   - Avtur: faktor emisi $3,16\text{ tCO}_2/\text{t}$.
   - Heavy Fuel Oil (HFO): faktor emisi $3,114\text{ tCO}_2/\text{t}$.
   - Marine Gas Oil (MGO): faktor emisi $3,206\text{ tCO}_2/\text{t}$.
   Emisi dihitung sebagai: $\text{Emisi } (\text{kgCO}_2\text{e}) = \lfloor \text{Konsumsi} \times \text{Faktor Emisi} \rfloor$.
3. **Integrasi ke Modul ESG:** Hasil perhitungan emisi leg dilaporkan ke modul ESG menggunakan kontrak `Modules\Esg\Contracts\EmissionBenefitRecorder` untuk agregasi Scope 1 korporat.

## Konsekuensi
Menyediakan visibilitas audit jejak karbon yang akurat dan mendukung pemeringkatan Carbon Intensity Indicator (CII) kapal.
