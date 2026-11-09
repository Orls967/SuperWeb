# Glosarium Istilah Medis, Asuransi & Referensi Regulasi Kesehatan

## 1. Glosarium Istilah Teknis & Singkatan Klinis

Kamus istilah ini berfungsi sebagai acuan baku bagi pengembang perangkat lunak, analis sistem, dokter penanggung jawab, koder medis, perawat, dan staf keuangan rumah sakit:

### A. Terminologi Pembiayaan & Asuransi Kesehatan
- **INA-CBG (Indonesian Case Base Groups)**: Sistem pembayaran paket prospektif berbasis kelompok kasus serupa yang digunakan oleh BPJS Kesehatan untuk membayar rumah sakit berdasarkan diagnosis dan prosedur tindakan medis, bukan berdasarkan rincian tagihan per item (*fee-for-service*).
- **MDC (Major Diagnostic Category)**: Pembagian kategori besar sistem organ (misalnya penyakit sistem kardiovaskular, respirasi, sistem saraf) yang menjadi dasar penentuan kode INA-CBG.
- **Severity Level (Tingkat Keparahan)**: Indeks derajat keparahan penyakit pasien yang dipengaruhi oleh adanya komorbiditas atau komplikasi:
  - *Severity I (Ringan)*: Tanpa komplikasi signifikan.
  - *Severity II (Sedang)*: Disertai komplikasi sedang (*Complication / Comorbidity* / CC).
  - *Severity III (Berat)*: Disertai komplikasi mayor atau gagal organ (*Major Complication / Comorbidity* / MCC).
- **CoB (Coordination of Benefits / Koordinasi Manfaat)**: Mekanisme penjaminan bersama di mana dua atau lebih penjamin (misal: BPJS Kesehatan sebagai penjamin primer dan Asuransi Swasta sebagai penjamin sekunder) berbagi beban pembiayaan pelayanan kesehatan pasien tanpa melebihi total tagihan riil.
- **Cashless Guarantee (Jaminan Non-Tunai)**: Surat jaminan pembayaran yang diterbitkan oleh asuransi atau TPA kepada rumah sakit, membebaskan pasien dari kewajiban membayar uang muka saat admisi rawat inap.
- **GL (Guarantee Letter / Surat Jaminan)**: Dokumen otorisasi penjaminan resmi yang memuat batas plafon biaya (*approved limit*), masa berlaku, dan klausul manfaat.
- **TPA (Third-Party Administrator)**: Badan usaha pihak ketiga yang mengelola administrasi klaim asuransi kesehatan, memverifikasi kelaikan peserta, dan menerbitkan surat jaminan atas nama perusahaan asuransi atau korporasi.
- **Out-of-Pocket (OOP)**: Biaya pelayanan kesehatan yang dibayarkan secara langsung oleh pasien dari kantong pribadi karena tidak ditanggung oleh asuransi atau melebihi batas plafon polis.
- **Deductible**: Jumlah biaya pengobatan awal tertentu yang wajib dibayar sendiri oleh pasien setiap tahun polis sebelum asuransi mulai menanggung biaya sisa.
- **Coinsurance**: Persentase pembagian biaya antara pasien dan perusahaan asuransi setelah deductible terpenuhi (misalnya: asuransi menanggung 80%, pasien menanggung 20%).
- **Stop-Loss Protection**: Skema mitigasi risiko keuangan di mana kerugian klaim yang melebihi batas tertentu ditanggung oleh pihak reasuransi guna melindungi likuiditas rumah sakit.

---

### B. Terminologi Pelayanan Klinis, Kamar Bedah & Keselamatan Pasien
- **UDI (Unique Device Identification)**: Sistem penomoran unik global berbasis standar GS1 untuk mengidentifikasi alat kesehatan dan implan secara presisi di seluruh rantai pasok medis.
- **Traceability Audit Token**: Token hash digital unik yang mengaitkan nomor seri implan bedah ke rekam medis pasien untuk pemantauan jangka panjang (*post-market surveillance*).
- **Hemovigilance**: Sistem surveilans pengawasan mutu menyeluruh terhadap rantai transfusi darah, mulai dari seleksi pendonor, pengolahan komponen darah, penyimpanan, hingga pencegahan efek samping pada resipien.
- **Crossmatch (Uji Silang Serologis)**: Pemeriksaan laboratorium pra-transfusi untuk memastikan serum resipien tidak mengandung antibodi yang menghancurkan eritrosit donor (*Crossmatch Mayor*) dan serum donor tidak bereaksi terhadap eritrosit pasien (*Crossmatch Minor*).
- **TRALI (Transfusion-Related Acute Lung Injury)**: Komplikasi sindrom gawat napas akut akibat reaksi imunologis pasca-transfusi darah.
- **TACO (Transfusion-Associated Circulatory Overload)**: Edema paru kardiogenik akut akibat pemberian volume darah atau cairan intravena yang terlalu cepat atau berlebihan.
- **SOFA Score (Sequential Organ Failure Assessment)**: Sistem penilaian berbasis bukti klinis untuk mengukur derajat disfungsi 6 sistem organ vital (respirasi, koagulasi, hepar, kardiovaskular, SSP, ginjal) pada pasien kritis di ICU.
- **ESI (Emergency Severity Index)**: Algoritma triase IGD 5 tingkat yang mengklasifikasikan pasien berdasarkan kegawatan klinis dan prediksi jumlah sumber daya penunjang yang dibutuhkan.
- **WHO Surgical Safety Checklist**: Lembar panduan keselamatan bedah yang terdiri dari fase *Sign-In* (pra-induksi), *Time-Out* (pra-insisi), dan *Sign-Out* (pra-penutupan rongga bedah).

---

### C. Terminologi Logistik Rantai Dingin & Lingkungan Hidup
- **Cold Chain (Rantai Dingin)**: Rangkaian proses logistik penyimpanan dan pengangkutan temperatur terkontrol untuk menjaga integritas produk farmasi biologi dan vaksin.
- **MKT (Mean Kinetic Temperature / Suhu Kinetik Rata-rata)**: Temperatur isotermal tunggal hipotetis yang merepresentasikan efek kinetika degradasi termal akibat fluktuasi suhu nyata dalam suatu periode penyimpanan, dihitung menggunakan persamaan Arrhenius.
- **Thermal Excursion**: Kejadian penyimpangan temperatur di luar rentang spesifikasi yang diizinkan untuk suatu komoditas obat sensitif suhu.
- **Festronik**: Sistem Elektronik Manifes Limbah Bahan Berbahaya dan Beracun (B3) resmi dari Kementerian Lingkungan Hidup dan Kehutanan (KLHK).
- **Chain of Custody (Rantai Kustodi)**: Dokumentasi kronologis yang tidak terputus yang menunjukkan penguasaan, pemindahan, analisis, dan pemusnahan limbah B3.
- **Autoclave**: Bejana uap bertekanan tinggi untuk mensterilkan instrumen bedah atau mendekontaminasi limbah medis infeksius sebelum pencacahan.
- **SAL (Sterility Assurance Level)**: Probabilitas bertahannya mikroorganisme viabel tunggal setelah proses sterilisasi (standar sterilitas instrumen kamar bedah: $\text{SAL} \le 10^{-6}$).
- **NBD (Nilai Batas Dosis)**: Batas paparan radiasi pengion kumulatif maksimum yang diizinkan oleh BAPETEN bagi pekerja radiasi rumah sakit ($20 \text{ mSv/tahun}$).

---

## 2. Daftar Referensi Regulasi Hukum & Standar Pelayanan Kesehatan

Seluruh arsitektur kode dan logika bisnis pada modul `Hospital` Fase 88 dirancang dengan merujuk pada regulasi perundang-undangan Republik Indonesia yang sah:

1. **Undang-Undang Republik Indonesia No. 17 Tahun 2023 tentang Kesehatan**:
   - Landasan penyelenggaraan pelayanan kesehatan terintegrasi, digitalisasi rekam medis, dan standar keselamatan pasien nasional.
2. **Peraturan Menteri Kesehatan RI No. 3 Tahun 2023 tentang Standar Tarif Pelayanan Kesehatan dalam Penyelenggaraan Program Jaminan Kesehatan**:
   - Pedoman resmi pengelompokan INA-CBG, regionalisasi tarif Zona 1 s/d Zona 5, klasifikasi rumah sakit, dan formula batasan selisih kenaikan kelas rawat inap.
3. **Peraturan Menteri Kesehatan RI No. 26 Tahun 2021 tentang Pedoman Indonesian Case Base Groups (INA-CBG)**:
   - Ketentuan koding ICD-10, ICD-9-CM, Berita Acara Kesepakatan Bersama Kemenkes dan BPJS Kesehatan, serta tata cara penyelesaian sengketa klaim (dispute).
4. **Peraturan Menteri Lingkungan Hidup dan Kehutanan RI No. P.56/Menlhk-Setjen/2015 tentang Tata Cara dan Persyaratan Teknis Pengelolaan Limbah Bahan Berbahaya dan Beracun dari Fasilitas Pelayanan Kesehatan**:
   - Standar teknis TPS limbah medis B3, ambang batas waktu simpan 48 jam suhu ruang vs 90 hari suhu dingin, pemilahan kantung warna limbah, dan manifes Festronik.
5. **Peraturan Menteri Kesehatan RI No. 4 Tahun 2016 tentang Penggunaan Gas Medik dan Vakum Medik pada Fasilitas Pelayanan Kesehatan**:
   - Standar teknis sistem sentral pipa gas oksigen, nitrous oxide, medical air, dan sistem pembuangan sisa gas anestesi.
6. **Peraturan Badan Pengawas Tenaga Nuklir (BAPETEN) No. 4 Tahun 2013 tentang Keselamatan Radiasi dalam Pemanfaatan Tenaga Nuklir**:
   - Regulasi Nilai Batas Dosis (NBD) pekerja radiasi $20 \text{ mSv/tahun}$, dosimetri badge TLD, dan proteksi kehamilan.
7. **Pernyataan Standar Akuntansi Keuangan (PSAK) No. 71 tentang Instrumen Keuangan**:
   - Pedoman pengakuan cadangan penurunan nilai piutang ragu berbasis kerugian kredit ekspektasian (*Expected Credit Loss* / ECL) pada portofolio piutang klaim rumah sakit.
