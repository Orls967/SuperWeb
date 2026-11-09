# Tata Kelola Klinis, Surveilans Infeksi & Keselamatan Pasien Rumah Sakit

## 1. Penelusuran Implan Bedah Berbasis Unique Device Identification (UDI)

Dalam rangka menegakkan Permenkes Standar Keselamatan Pasien dan ketentuan *Post-Market Surveillance* alat kesehatan, rumah sakit menerapkan sistem penelusuran implan digital otomatis melalui `HospitalSurgicalImplantTraceabilityService`.

### Komponen Pengawasan Implan Kamar Bedah
1. **Nomor Izin Edar Kemenkes RI (AKL / AKD)**:
   - Setiap implan yang digunakan (seperti stent koroner, pen pelat titanium ortopedi, sendi lutut buatan, lensa intraokular) wajib diverifikasi nomor izin edarnya (`AKL` untuk alat kesehatan impor, `AKD` untuk alat kesehatan produksi dalam negeri).
2. **Barcode GS1 Unique Device Identification (UDI)**:
   - Memuat Application Identifier (AI):
     - `(01)`: Global Trade Item Number (GTIN).
     - `(17)`: Tanggal Kedaluwarsa Sterilisasi (YYMMDD).
     - `(10)`: Nomor Lot / *Batch Number* Pabrik.
     - `(21)`: Nomor Seri Unik Perangkat Medis.
3. **Penyaringan Penarikan Produk (Product Recall Screening)**:
   - Sebelum implan diserahkan ke meja bedah steril, sistem mencocokkan nomor lot dengan *database alert* penarikan produk Badan POM dan produsen global.
   - Jika nomor lot terdaftar dalam daftar recall, sistem langsung memblokir penggunaan dan menerbitkan notifikasi karantina implan.
4. **Token Kustodi Rekam Medis (Traceability Audit Token)**:
   - Setiap pemasangan implan yang lolos verifikasi menghasilkan token audit permanen berbasis SHA-256 (`IMP-TRACE-...`) yang dicatat pada berkas resume medis bedah pasien dan rincian folio penagihan.

---

## 2. Hemovigilance & Keamanan Transfusi Bank Darah Rumah Sakit (BDRS)

Keselamatan transfusi darah dikelola oleh `BloodBankHemovigilanceService` guna mencegah insiden fatal ketidakcocokan serologis (*ABO Incompatibility*) dan reaksi hemolitik akut.

### Tahapan Verifikasi Sebelum Pengeluaran Darah (*Blood Bag Release*)
1. **Uji Silang Serologis (Crossmatch Compatibility)**:
   - **Crossmatch Mayor**: Mencampurkan suspensi sel darah merah donor dengan serum darah pasien. Jika timbul aglutinasi atau hemolisis, reaksi dinyatakan **POSITIF** dan darah mutlak **DILARANG** ditransfusikan.
   - **Crossmatch Minor**: Mencampurkan serum plasma donor dengan sel darah merah pasien untuk mendeteksi antibodi atipikal.
2. **Kepatuhan Rantai Dingin Produk Darah**:
   - Sel Darah Merah Pekat (*Packed Red Cells* / PRC): Disimpan pada suhu $+2.0^\circ\text{C} \text{ s/d } +6.0^\circ\text{C}$.
   - Plasma Beku Segar (*Fresh Frozen Plasma* / FFP): Disimpan beku pada suhu $\le -18.0^\circ\text{C}$.
   - Konsentrat Trombosit (*Thrombocyte Concentrate* / TC): Disimpan pada suhu kamar $+20.0^\circ\text{C} \text{ s/d } +24.0^\circ\text{C}$ dengan pengocokan kontinu (*agitator*).
3. **Audit Hash Transfusi Darah**:
   - Setiap pengeluaran darah menghasilkan hash unik `HEMOVIGILANCE-SHA256` yang mengunci barcode kantung darah, golongan darah donor/resipien, dan identitas petugas verifikator.

---

## 3. Sistem Pengawasan Gas Medis Sentral & Deteksi Kebocoran Pipa

Instalasi Gas Medis Sentral Rumah Sakit (Permenkes No. 4/2016 & HTM 02-01) diawasi oleh `MedicalGasPipelineSystemMonitorService`:

### Spesifikasi Tekanan Operasional Standar
- **Oksigen Medis ($\text{O}_2$)**: $4.0 \text{ s/d } 5.2 \text{ bar}$. Tekanan di bawah $4.0 \text{ bar}$ membunyikan alarm kritis (*Critical Low Pressure*) untuk segera mengaktifkan manifold cadangan.
- **Medical Compressed Air (4-bar)**: $3.8 \text{ s/d } 4.8 \text{ bar}$ untuk menyuplai ventilator ICU.
- **Surgical Compressed Air (7-bar)**: $6.5 \text{ s/d } 7.8 \text{ bar}$ untuk menggerakkan alat bor bedah ortopedi.
- **Nitrous Oxide ($\text{N}_2\text{O}$)**: $4.0 \text{ s/d } 5.0 \text{ bar}$ untuk anestesi inhalasi.
- **Medical Vacuum (Tekanan Negatif)**: $-0.85 \text{ s/d } -0.55 \text{ bar}$ untuk hisap lendir dan cairan pembedahan.

Sistem juga mendeteksi dugaan kebocoran pipa (*pipe rupture*): jika laju aliran gas ($L/\text{menit}$) melampaui $2.5 \times$ dari kapasitas konsumsi normal jumlah ventilator aktif, alarm investigasi kebocoran pipa zona segera diaktifkan.

---

## 4. Stratifikasi Keparahan Pasien Kritis (SOFA Scoring ICU)

Untuk mendukung justifikasi medis add-on rawat intensif pada klaim INA-CBG dan asuransi swasta, `CriticalCareOrganSupportScoringService` mengkalkulasi skor *Sequential Organ Failure Assessment* (SOFA) dari 6 sistem organ:
- **Respirasi**: Rasio $\text{PaO}_2 / \text{FiO}_2$ (skor 1 s/d 4).
- **Koagulasi**: Jumlah trombosit ($< 150, < 100, < 50, < 20 \times 10^3/\mu\text{L}$).
- **Hepar**: Bilirubin serum ($\ge 1.2, \ge 2.0, \ge 6.0, \ge 12.0 \text{ mg/dL}$).
- **Kardiovaskular**: Tekanan darah MAP $< 70 \text{ mmHg}$ atau penggunaan vasopresor (Dopamine, Norepinephrine).
- **Saraf Pusat**: Skala Koma Glasgow (GCS $13-14, 10-12, 6-9, < 6$).
- **Ginjal**: Kreatinin serum ($\ge 1.2, \ge 2.0, \ge 3.5, \ge 5.0 \text{ mg/dL}$) atau oliguria urin $< 200 \text{ mL/hari}$.

Skor total SOFA $\ge 12$ mencerminkan kondisi gagal multi-organ kritis (*Multi-Organ Failure*) dengan estimasi mortalitas $\ge 80\%$, memberikan pembuktian objektivitas klinis yang mutlak diperlukan saat menghadapi dispute klaim.

---

## 5. WHO Surgical Safety Checklist (Tiga Tahap Keselamatan Kamar Bedah)

Layanan `HospitalSurgicalSafeSurgeryChecklistService` membagi verifikasi keselamatan operasi ke dalam 3 fase mutlak:
1. **Sign-In (Sebelum Induksi Anestesi)**:
   - Konfirmasi verbal identitas pasien dan gelang rekam medis.
   - Penandaan lokasi sayatan operasi (*site marking*) telah terverifikasi.
   - Mesin anestesi, sirkuit pernapasan, dan pulse oximeter berfungsi normal.
2. **Time-Out (Sebelum Sayatan Kulit Pertama)**:
   - Seluruh anggota tim (operator bedah, penata anestesi, perawat sirkuler) memperkenalkan diri.
   - Konfirmasi verbal nama pasien, prosedur tindakan bedah, dan posisi anatomis.
   - Antibiotik profilaksis telah diberikan dalam kurun 60 menit sebelum insisi.
   - Antisipasi kehilangan darah kritis ($> 500 \text{ mL}$) dan kesiapan kantung darah.
3. **Sign-Out (Sebelum Pasien Meninggalkan Ruang Operasi)**:
   - Pencocokan hitungan kasa steril, jarum bedah, dan instrumen klem antara awal dan akhir operasi.
   - Pelabelan wadah spesimen patologi anatomi (nama pasien, nomor RM, lokasi biopsi).
   - Penentuan zona transfer pasca-bedah (PACU ruang pulih sadar atau ICU intensif).

---

## 6. Dosimetri & Keselamatan Radiasi Pekerja RS (Regulasi BAPETEN No. 4/2013)

Layanan `HospitalRadioprotectionDosimetryService` memastikan keselamatan pekerja radiasi di unit CT-Scan, Fluoroskopi Cath Lab, Radioterapi Linac, dan Kedokteran Nuklir:
- **Nilai Batas Dosis (NBD) Tahunan**: Dosis efektif seluruh tubuh maksimal **$20.0 \text{ mSv/tahun}$**.
- **Ambang Batas Investigasi Internal**: Dosis kuartalan $\ge 4.5 \text{ mSv}$ atau bulanan $\ge 1.5 \text{ mSv}$ wajib memicu audit investigasi pelindung radiasi Pb dan uji kebocoran tabung sinar-X.
- **Proteksi Pekerja Radiasi Hamil**: Batas dosis kumulatif janin dibatasi maksimum **$1.0 \text{ mSv}$** sepanjang masa kehamilan. Pekerja hamil yang mencapai batas ini segera dimutasi ke bagian non-radiasi demi mencegah malformasi teratogenik janin.
