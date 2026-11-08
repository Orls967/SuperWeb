# KEPUTUSAN ARSITEKTUR G7-D49: STANDAR PENILAIAN INTENSITAS KARBON OPERASIONAL (IMO CII) & DUAL-FUEL MARITIM

**Status:** `🔵 DISETUJUI & DIIMPLEMENTASIKAN`  
**Fase:** Fase 180 (Armada Kapal, Voyage, Charter & Marine Services)  
**Terkait:** Lini 27 (Maritim & Pelabuhan), Kontrak K3 (Modul ESG), Algoritma A12 (`CiiCarbonIntensityRatingEngine`)  

---

## 1. Konteks Bisnis & Regulasi Lingkungan Global

Organisasi Maritim Internasional (IMO) telah memberlakukan amandemen ketat terhadap **MARPOL Annex VI (Regulation 28)** yang mewajibkan seluruh kapal niaga dengan bobot 5.000 Gross Tonnage (GT) ke atas untuk:
1. Menghitung indikator intensitas karbon operasional tahunan (*Operational Carbon Intensity Indicator / Attained CII*).
2. Membandingkan Attained CII dengan nilai referensi yang disyaratkan (*Required CII*) yang diperketat setiap tahun dengan faktor reduksi $Z$ (5% di tahun 2023 hingga 11% di tahun 2026).
3. Menerima peringkat kinerja karbon tahunan dari huruf **A (Major Superior)** hingga **E (Inferior)**.
4. Kapal yang memperoleh peringkat D selama 3 tahun berturut-turut atau peringkat E dalam 1 tahun dilarang berlayar di perairan teritorial negara anggota IMO kecuali telah menyampaikan *Ship Energy Efficiency Management Plan (SEEMP Part III) Corrective Action Plan* yang telah diverifikasi oleh otoritas maritim / Biro Klasifikasi.

Di sisi lain, armada modern mulai mengadopsi bahan bakar transisi rendah karbon (*Dual-Fuel*: VLSFO, LSMGO, Biofuel blend B30 bersertifikasi ISCC, dan LNG). SuperWeb perlu mengaudit performa emisi ini dan menghubungkannya dengan modul ESG (`EmissionBenefitRecorder`) agar kapal dengan rating superior (A atau B) dapat mengklaim sertifikat kredit karbon laut (*Blue Carbon / Avoided Maritime Emissions*).

---

## 2. Keputusan Desain & Arsitektur

Diputuskan untuk menerapkan arsitektur audit emisi maritim terpadu berbasis regulasi **IMO Resolutions MEPC.336(76), MEPC.338(76), dan MEPC.354(78)**:

1. **Mesin Audit Intensitas Karbon (Algoritma Bersertifikat A12):**
   - Diimplementasikan pada kelas terverifikasi `Modules\Shared\Domain\Algorithms\Port\CiiCarbonIntensityRatingEngine`.
   - Menghitung emisi karbon absolut dengan faktor konversi bahan bakar $C_F$ menurut MEPC.308(73):
     - VLSFO: $3.1510 \text{ t-CO}_2 / \text{t-Fuel}$
     - LSMGO: $3.2060 \text{ t-CO}_2 / \text{t-Fuel}$
     - LNG: $2.7500 \text{ t-CO}_2 / \text{t-Fuel}$
     - Biofuel B30: $2.4500 \text{ t-CO}_2 / \text{t-Fuel}$
   - Menghitung transport work kapal: $\text{Kapasitas (DWT)} \times \text{Jarak Tempuh (NM)}$.
   - Menentukan garis batas (*boundaries*) A, B, C, D menggunakan koefisien eksponensial $d_1, d_2, d_3, d_4$ spesifik untuk setiap jenis kapal (*Bulk Carrier, Container Ship, Product Tanker, General Cargo, LPG Carrier*).

2. **Integrasi Lintas Modul ke ESG (EmissionBenefitRecorder K3):**
   - Dikelola oleh `Modules\Port\Application\Services\MaritimeEsgCiiService`.
   - Ketika kapal mencapai peringkat A atau B (kinerja efisiensi superior di atas ambang regulasi IMO), selisih emisi CO2 yang berhasil dihemat dihitung secara otomatis.
   - Panggilan otomatis dieksekusi ke kontrak `EmissionBenefitRecorder::recordAvoidedEmission` dengan kode sumber `FLEET_VESSEL_CII` dan kunci idempoten berformat `CII-{IMO}-{YEAR}`.

3. **Tindakan Korektif Terpadu (SEEMP Part III Integration):**
   - Bila kapal terancam jatuh ke peringkat D atau E, sistem menghitung rekomendasi penurunan kecepatan (*recommended speed reduction percentage*) berdasarkan korelasi propulsi kuadratik.
   - Sistem merekomendasikan opsi penyambungan listrik darat hijau (*Cold Ironing*) saat sandar di pelabuhan melalui `ColdIroningShorePowerService` untuk mengeliminasi emisi mesin bantu.

---

## 3. Konsekuensi Arsitektural

- **Positif:** Armada kapal yang dikelola SuperWeb memiliki kepatuhan hukum penuh terhadap IMO MARPOL Annex VI, menghindari risiko penahanan kapal oleh *Port State Control (PSC)*, dan menciptakan aliran pendapatan nilai tambah melalui tokenisasi kredit emisi karbon terhindar di modul ESG.
- **Batasan:** Data jarak dan konsumsi bahan bakar dari *Noon Reports* harus diinput secara disiplin setiap hari oleh perwira kapal untuk menjaga keakuratan akumulasi tahunan.
