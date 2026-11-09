# Manajemen Persediaan FEFO, Routing Multi-Cabang & Rantai Dingin (Cold-Chain)

Dokumen ini menguraikan arsitektur penanganan logistik farmasi, perutean multi-cabang berbasis geolokasi, mekanisme First-Expired-First-Out (FEFO), dan pemantauan rantai dingin telemetris pada modul Hospital Fase 104.

---

## 1. Prinsip FEFO (First-Expired, First-Out)

Berbeda dengan sistem gudang umum yang menggunakan FIFO (First-In, First-Out), instalasi farmasi rumah sakit dan jaringan apotek wajib menerapkan FEFO untuk meminimalkan risiko obat kedaluwarsa (*spoilage / expiry write-off*) serta memastikan kepatuhan regulasi BPOM.

### Aturan Eksekusi FEFO:
1. **Urutan Pemilihan Lot**:
   Lot dengan tanggal kedaluwarsa (`expiry_date`) paling awal selalu dipotong terlebih dahulu:
   ```sql
   SELECT * FROM hsp_pharmacy_branch_lots
   WHERE branch_id = :branchId AND drug_code = :drugCode AND is_quarantined = false AND quantity > 0
   ORDER BY expiry_date ASC
   FOR UPDATE;
   ```
2. **Karantina Otomatis**:
   Lot obat yang memiliki sisa masa simpan kurang dari batas ambang minimum (misalnya < 60 hari untuk sirup/antibiotik) atau yang mengalami pelanggaran suhu langsung dikarantina (`is_quarantined = true`) dan dikeluarkan dari perhitungan stok yang tersedia untuk dispensing.
3. **Pemberian Sebagian (Partial Lot Picking)**:
   Bila satu lot tidak mencukupi kuantitas resep, sistem membagi alokasi ke lot berikutnya secara deterministik tanpa meninggalkan sisa pecahan negatif.

---

## 2. Perutean Cerdas Multi-Cabang (Geospatial Routing)

Ketika resep elektronik diterbitkan melalui telekonsultasi, pesanan harus dialokasikan ke cabang apotek yang paling optimal berdasarkan kriteria gabungan:
- Jarak geografis terdekat dengan alamat pasien (Haversine distance).
- Ketersediaan stok lengkap seluruh item resep.
- Ketersediaan fasilitas pendingin khusus (*cold-chain storage*) bila ada obat rantai dingin.

### Formula Jarak Haversine:
$$d = 2R \arcsin \left( \sqrt{\sin^2\left(\frac{\Delta \phi}{2}\right) + \cos(\phi_1)\cos(\phi_2)\sin^2\left(\frac{\Delta \lambda}{2}\right)} \right)$$
dengan $R = 6.371\text{ km}$.

Bila cabang lokal terdekat kehabisan stok, `PharmacyInterBranchTransferCoordinator` mengevaluasi opsi *cross-docking* atau mengalihkan pesanan ke Cabang Hub Regional terdekat.

---

## 3. Logistik Rantai Dingin (Cold-Chain Logistics)

Obat biologi seperti insulin, vaksin, albumin, dan serum antibodi monoklonal wajib disimpan dan ditransportasikan pada suhu ketat $2.0^\circ\text{C} - 8.0^\circ\text{C}$.

```
[Apotek Pengirim] ────(Insulated Box + Ice Pack)────> [Kurir Logistik] ────> [Pasien]
      ▲                                                      │
      │                                             (Sensor IoT Suhu)
      │                                                      │
[Telemetri Realtime: 2°C - 8°C] <────────────────────────────┘
      │
      ├── Bila Suhu Normal: Terus melaju hingga e-POD ditandatangani
      └── Bila Breach (>8°C > 5 min): Karantina Seketika & Gagalkan Serah Terima
```

---

## 4. Mean Kinetic Temperature (Persamaan Arrhenius)

Untuk menilai dampak akumulasi fluktuasi termal selama transit, digunakan persamaan *Mean Kinetic Temperature* (MKT):

$$T_{MKT} = \frac{\Delta H / R}{-\ln \left( \frac{\sum_{i=1}^n e^{-\Delta H / (R \cdot T_i)}}{n} \right)}$$

Parameter standar industri farmasi (USP):
- $\Delta H$: Energi aktivasi degradasi senyawa biologis ($83.144\text{ kJ/mol}$).
- $R$: Konstanta gas universal ($8.3144\text{ J/(mol}\cdot\text{K)}$).
- $T_i$: Pembacaan temperatur ke-$i$ dalam skala Kelvin ($^\circ\text{C} + 273.15$).

Bila nilai $T_{MKT}$ melebihi $8.0^\circ\text{C}$ atau terjadi deviasi suhu ekstrim ($> 15.0^\circ\text{C}$ sesaat), paket otomatis ditandai `QUARANTINED_BREACH` dan kurir dilarang menyerahkan obat kepada pasien.

---

## 5. Electronic Proof of Delivery (e-POD)

Serah terima obat keras ke tangan pasien diwajibkan mencatat bukti digital otentik (`MedicineCourierDelivery`):
1. **Nomor Induk Kependudukan (NIK)** 16 digit dari penerima paket.
2. **Koordinat GPS** saat kurir menekan tombol konfirmasi penyerahan.
3. **Cryptographic Signature Hash**:
   $$\text{Hash}_{POD} = \text{SHA256}(\text{DeliveryCode} \parallel \text{NIK} \parallel \text{Token} \parallel \text{Timestamp})$$
4. **Pembaruan Status**: Order berpindah ke `DELIVERED`, persediaan dipotong secara permanen dari buku besar, dan notifikasi konfirmasi dikirim ke dokter penerbit resep.

