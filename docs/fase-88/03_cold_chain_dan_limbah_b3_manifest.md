# Pemantauan Rantai Dingin Farmasi & Rantai Kustodi Festronik Limbah Medis B3

## 1. Pemantauan Rantai Dingin Farmasi (Cold Chain Telemetry System)

Sistem Rantai Dingin (*Cold Chain*) rumah sakit bertugas menjaga temperatur penyimpanan obat biologis, vaksin, insulin, serum antibodi monoklonal, dan produk darah dalam batas yang ditentukan oleh regulasi Badan Pengawas Obat dan Makanan (BPOM) serta pedoman *World Health Organization* (WHO).

### Klasifikasi Unit Penyimpanan Dingin & Rentang Standar
| Kategori Peralatan | Rentang Suhu Operasional | Komoditas Medis yang Disimpan | Toleransi Ekskursi Maksimal |
| :--- | :--- | :--- | :--- |
| **ULT Freezer** | $-86.0^\circ\text{C} \text{ s/d } -60.0^\circ\text{C}$ | Vaksin mRNA, jaringan sel punca / *stem cell* | 15 menit pada $>-50^\circ\text{C}$ |
| **Biomedical Deep Freezer** | $-25.0^\circ\text{C} \text{ s/d } -15.0^\circ\text{C}$ | Vaksin polio oral (OPV), plasma beku darah (FFP) | 30 menit pada $>-10^\circ\text{C}$ |
| **Pharmaceutical Refrigerator** | $+2.0^\circ\text{C} \text{ s/d } +8.0^\circ\text{C}$ | Vaksin DPT-HB-Hib, Campak, BCG, Rabies, Insulin | 45 menit pada $>+8.0^\circ\text{C}$ |
| **Platelet Incubator & Agitator** | $+20.0^\circ\text{C} \text{ s/d } +24.0^\circ\text{C}$ | Konsentrat trombosit (*Thrombocyte Concentrate*) | 30 menit tanpa agitasi |
| **Controlled Ambient Room** | $+15.0^\circ\text{C} \text{ s/d } +25.0^\circ\text{C}$ | Larutan infus parenteral, salep steril, reagen lab | 120 menit pada $>+30^\circ\text{C}$ |

---

### Perhitungan Mean Kinetic Temperature (MKT)
Untuk mengukur dampak kumulatif fluktuasi temperatur terhadap stabilitas molekul protein obat biologi, layanan `VaccineThermalStabilityCalculator` mengimplementasikan persamaan kinetika degradasi Arrhenius sesuai standar *United States Pharmacopeia* (USP Bab <1150>):

$$T_{\text{MKT}} = \frac{\frac{\Delta H}{R}}{-\ln \left( \frac{\sum_{i=1}^{n} e^{-\frac{\Delta H}{R \cdot T_i}}}{n} \right)}$$

Keterangan Parameter Fisikokimia:
- $\Delta H$: Energi aktivasi degradasi senyawa biologi (standar farmakope: $83.144 \text{ kJ/mol}$).
- $R$: Konstanta gas universal ($8.3144 \text{ J/(mol}\cdot\text{K)}$).
- $T_i$: Temperatur terukur pada interval waktu ke-$i$ dalam skala Kelvin ($T_{\text{K}} = T_{^\circ\text{C}} + 273.15$).
- $n$: Jumlah titik sampel telemetri suhu selama interval evaluasi.

Jika nilai $T_{\text{MKT}}$ terhitung melampaui $+8.0^\circ\text{C}$ untuk komoditas vaksin chiller $+2^\circ\text{C} \text{ s/d } +8^\circ\text{C}$, produk dianggap mengalami kerusakan termal kinetik dan wajib ditarik dari peredaran.

---

### Alur Kerja Karantina Otomatis (Automatic Quarantine Protocol)
1. **Penerimaan Telemetri**: Data logger atau probe sensor IoT PT100/NTC mengirimkan sinyal suhu setiap 300 detik (5 menit).
2. **Evaluasi Anomali**: Jika suhu terbaca melampaui batas kritis ($T > 12.0^\circ\text{C}$ atau $T < 1.0^\circ\text{C}$ risiko pembekuan vaksin sensitif beku), `ColdChainMonitoringService` membunyikan alarm insiden.
3. **Penguncian Lot Inventaris**:
   - Status lemari pendingin pada tabel `hsp_med_fridge_breaches` diperbarui menjadi `QUARANTINED`.
   - Kolom `is_quarantined` pada tabel `hsp_branch_inventory_lots` diset bernilai `true` untuk seluruh obat yang berada di unit tersebut.
   - Farmasis dan kasir otomatis dicegah meresepkan atau mendistribusikan obat dari lot yang dikarantina.
4. **Prosedur Pembebasan (Clearance)**:
   - Sampel obat diuji stabilitasnya oleh Laboratorium Pengendalian Mutu.
   - Farmasis Penanggung Jawab memasukkan kode protokol stabilitas (`PROTOCOL-STABILITY-PASS`) melalui method `releaseFromQuarantine()`.
   - Karantina dicabut dan status inventaris dipulihkan kembali ke status operasional.

---

## 2. Rantai Kustodi Festronik Limbah Medis B3 (Permen LHK No. P.56/2015)

Pengelolaan limbah medis rumah sakit diatur secara ketat untuk mencegah kontaminasi silang lingkungan, penularan penyakit menular (seperti Hepatitis B, Hepatitis C, HIV), dan penyalahgunaan jarum suntik bekas.

### Klasifikasi Warna Wadah & Karakteristik Limbah
| Kategori Limbah B3 Medis | Kode Warna Kantung / Wadah | Jenis Material & Contoh Nyata |
| :--- | :--- | :--- |
| **Limbah Infeksius** | Kantung Plastik Kuning Tebal | Kasa bernoda darah, perban bedah, sarung tangan steril kotor, selang kateter |
| **Benda Tajam (Sharps)** | Wadah Kuning Tahan Tusuk (*Safety Box*) | Jarum suntik sekali pakai, mata pisau bedah (*scalpel*), pecahan ampul obat |
| **Limbah Sitotoksik** | Kantung Plastik Ungu | Botol bekas kemoterapi kanker, selang infus onkologi, spuit sitostatika |
| **Limbah Kimia & Farmasi** | Kantung Plastik Cokelat | Reagen kimia kedokteran kedaluwarsa, sisa formalin patologi anatomi, obat expired |
| **Limbah Radioaktif** | Kontainer Bertimbal Khusus (*Lead Castle*) | Residu cairan Iodium-131, Technetium-99m dari unit kedokteran nuklir |

---

### Batas Regulasi Waktu Simpan TPS Limbah B3 Internal
Berdasarkan Pasal 8 Peraturan Menteri LHK No. P.56/Menlhk-Setjen/2015:
- **Kondisi Suhu Ruang ($T > 0^\circ\text{C}$)**: Limbah medis infeksius dan benda tajam **wajib dimusnahkan dalam waktu paling lama 48 jam**.
- **Kondisi Suhu Dingin ($T \le 0^\circ\text{C}$)**: Jika TPS B3 memiliki unit pendingin berinsulasi (*cold room* / *freezer* limbah medis), limbah dapat disimpan paling lama **90 hari** sebelum diserahkan ke transporter berizin.

---

### Rantai Kustodi Kriptografis SHA-256 (Chain of Custody & Tamper Detection)

Untuk memastikan keutuhan data manifes limbah dari hulu ke hilir, setiap peristiwa logistik dicatat secara berantai:

```
[Ruang Perawatan (Ward)]
  |-- Berat: W_ward
  |-- Hash_0 = SHA-256(ManifestNumber + W_ward + WardStaffName + Timestamp)
  v
[Penerimaan TPS Internal RS]
  |-- Berat Timbang Ulang: W_tps (Toleransi selisih max 5%)
  |-- Hash_1 = SHA-256(Hash_0 + W_tps + TpsOfficerName + TpsBinCode + Timestamp)
  v
[Serah Terima Transporter Berizin]
  |-- Berat Truk: W_truck
  |-- Hash_2 = SHA-256(Hash_1 + W_truck + TransporterCompany + DriverName + PlateNo + Timestamp)
  v
[Insinerasi Pemusnahan Akhir]
  |-- Suhu Ruang Bakar 2: >= 1000 °C
  |-- Hash_3 = SHA-256(Hash_2 + CertificateNo + IncineratorTempC + DestructionTimestamp)
```

### Algoritma Pemeriksaan Integritas Hash
Metode `verifyCustodyChainIntegrity(MedicalWasteManifest $manifest)` melakukan verifikasi rantai dari simpul pertama hingga simpul terakhir:
- Jika salah satu baris log diubah langsung pada database (misalnya nilai `weight_kg` dimanipulasi oleh oknum petugas TPS), kalkulasi ulang hash akan menghasilkan perbedaan nilai (*hash mismatch*).
- Sistem segera mengembalikan nilai `false` dan menerbitkan notifikasi audit forensik lingkungan.
