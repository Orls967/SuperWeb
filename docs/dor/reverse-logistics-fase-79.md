# Definition of Ready (DoR) — Fase 79: Reverse Logistics ke Bahan Baku Manufaktur (P1.1)

#### DoR Fase 79 — Reverse Logistics Vertical Slice
- **Disetujui pemilik:** Internal Gate G6-P1 (Pelaksana Pengembangan Vertical Slice)
- **Tujuan bisnis (1–2 kalimat):** Mengoperasikan rantai logistik sirkular di mana armada Logistik mengangkut barang retur dan limbah sekunder dari berbagai sumber (outlet Resto, bengkel AutoServe, pabrik, Store, dan Circular marketplace) menuju pabrik manufaktur untuk ditimbang, dinilai oleh Pricing Engine, diterima sebagai lot bahan baku append-only lewat kontrak K1 RecycledMaterialReceiver, dicatat manfaat emisinya ke ESG lewat kontrak K3, dan dialokasikan secara optimal menggunakan algoritma MinCostFlow bersertifikat dualitas Johnson.
- **Kutipan Dokumen Rujukan:**
  - `docs/KONSEP.md` §5A–§5F (baris 730–800): Skala logistik sirkular, alur reverse logistics ke bahan baku Manufaktur, akru carrier, posting clearing `mfg:recycled_material_clearing:IDR` dan `ap:supplier:{party}:IDR`, audit dua sumber `logi:circular-audit`.
  - `docs/KONSEP.md` §A1 (baris 100–105): Registrasi modul Logistics (`lgx_`), Manufacturing (`mfg_`), Pricing (`pric_`), Esg (`esg_`).
  - `docs/KONSEP.md` §A2 (baris 108–165): Aturan buku besar double-entry, saldo normal, larangan saldo negatif pada akun clearing/utang, audit dua sumber.
  - `docs/KONSEP.md` §A3 (baris 151–158): Satuan integer eksak (gram integer untuk berat neto, integer rupiah, integer bps).
  - `docs/KONSEP.md` §A4 (baris 181–210): Idempotency key berformat `{prefiks}:{aksi}:{id_bisnis}` tanpa nilai acak/waktu.
  - `docs/KONSEP.md` §A7: Penggunaan `ClockInterface` untuk waktu bisnis.
  - `docs/PROGRESS.md` Fase 79 (baris 8500–8575): Deliverable 79.1–79.8.
- **Modul pemilik & prefiks:**
  - Modul Utama: `Logistics` (`lgx_`)
  - Modul Aditif: `Manufacturing` (`mfg_`), `Pricing` (`pric_`), `Esg` (`esg_`), `Shared` (`Modules\Shared\Domain\Algorithms\Flow`)
- **Prasyarat & status:**
  - Trunk master hijau (commit `cba3f27c`).
  - Modul Logistics dasar, Manufacturing, Pricing, Esg beroperasi.
- **Item yang dikerjakan sesi ini:**
  - D79-1: Model `Shipment` reverse dengan `reverse_reason` in {retur, oli_bekas, jelantah, scrap, material_sekunder}, party sumber, dan siklus status `requested -> picked_up -> at_hub -> delivered_to_plant -> received | rejected` dengan custody hash-chain.
  - D79-2: Penerimaan di pabrik via kontrak K1 (`RecycledMaterialReceiver`), harga via K2 (`MaterialPriceQuote`), penimbangan gram integer dan grading; 422 bila harga belum ada.
  - D79-3: Posting akru carrier per leg (`lgx:carrier_cost` / `ap:carrier:{id}`), clearing penerimaan (`inv:materials` / `mfg:recycled_material_clearing`), dan nota sumber (`mfg:recycled_material_clearing` / `ap:supplier:{party}`).
  - D79-4: Pencatatan emisi lewat K3 (`EmissionBenefitRecorder`), faktor versi `esg_circular_factors`, dan skor sirkularitas bps.
  - D79-5: Alokasi tonase siap angkut via `Flow\MinCostFlow` dan verifikasi sertifikat dualitas `Flow\MinCostFlowCertificateVerifier`.
  - D79-6: Audit dua sumber `logi:circular-audit` dan fixture korupsi `CircularAuditCorruptionFixture`.
- **Item yang ditunda (Register Minus):**
  - Sumber internal grup lewat Intercompany (ditunda ke P2).

