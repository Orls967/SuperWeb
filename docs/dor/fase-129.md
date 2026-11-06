# Definition of Ready (DoR) — Fase 129: Microgrid, Storage & Resilience

## 1. Konteks Bisnis & Operasional
Modul Energi & Utilitas (`egy_`) bertanggung jawab atas ketahanan sistem daya (grid resilience), optimalisasi penyimpanan energi baterai (BESS), kesiapan genset darurat dan tes beban mingguan, partisipasi demand-response bersama PLN, serta prosedur pemulihan pemadaman total (black-start sequence).

## 2. Cakupan Fitur
- **129.1 Microgrid Controller & Islanding**:
  Deteksi transisi Grid-Connected <-> Islanded dengan shedding otomatis beban non-kritis (0/1 Knapsack berbobot prioritas).
- **129.2 BESS Arbitrage & Degradation**:
  Optimasi pengisian baterai off-peak dan pelepasan daya on-peak memperhitungkan biaya degradasi siklus sel baterai (Rainflow counting).
- **129.3 Backup Generation & Genset BBM Scope 1**:
  Pencatatan jam jalan mesin, konsumsi liter solar BBM, tes beban berkala mingguan, dan kalkulasi emisi Scope 1 langsung.
- **129.4 Demand-Response Aggregator**:
  Penerimaan sinyal curtailment dari PLN, pemangkasan beban fleksibel < 15 menit, dan pembukuan insentif kapasitas ke ledger.
- **129.5 Black-Start Sequencing**:
  Urutan pemulihan blackout mandiri (energized busbar primer -> isolasi generator -> sinkronisasi fasa -> re-energize feeder).
- **129.6 Acceptance Tests**:
  5 acceptance test skenario end-to-end + unit test algoritma bersertifikat.
- **129.7 Quality Gate**:
  Zero float, audit ledger seimbang, Pint passed, arch:scan bersih.

## 3. Kriteria Keberhasilan & Batasan
- Seluruh nilai kuantitas daya (Watt/kW), energi (kWh/MWh), kapasitas baterai, dan uang wajib INTEGER murni.
- Keputusan desain dicatat pada `docs/DECISIONS.md`.
