# Manual Rekayasa, Pemeliharaan Fasilitas & Otomatisasi Kamar Cerdas (Engineering Manual)

Dokumen ini memuat panduan teknis bagi tim Rekayasa (Engineering) dalam memelihara perangkat keras kamar, gateway IoT, sistem HVAC, dan kalibrasi sensor.

---

## 1. Arsitektur Perangkat Pintar Kamar (Smart Room Architecture)

Setiap unit kamar hotel terhubung ke jaringan lokal nirkabel menggunakan protokol Zigbee dan Bluetooth Low Energy (BLE) yang terintegrasi ke gateway sentral lantai:
1. **Sensor Gerak Pasif Inframerah (PIR Motion Sensor)**:
   - Terpasang di langit-langit koridor masuk kamar dan area ruang tidur utama.
   - Mengirimkan detak sinyal (*heartbeat*) setiap 60 detik jika mendeteksi pergerakan fisik tamu.
   - Jika dalam 15 menit tidak terdeteksi gerak saat kamar terisi, gateway memicu pesan ke algoritma H4 HVAC Setback Controller.
2. **Termostat Cerdas & Modulator Katup Fan Coil Unit (FCU)**:
   - Menerima instruksi kenaikan setpoint suhu (+3 °C) dari sistem sentral saat kamar kosong terisi (*occupied vacant*).
   - Katup pendingin air (chilled water valve) dimodulasi untuk menurunkan debit pendinginan, menghasilkan efisiensi daya listrik hingga 30%.
3. **Kunci Pintu Pintar (Smart Door Lock S1)**:
   - Ditenagai oleh 4 baterai alkaline AA bertegangan 1,5V dengan masa pakai rata-rata 12 bulan.
   - Sensor voltase baterai mengirimkan peringatan jika tegangan turun di bawah 4,8V.
   - Mekanisme penguncian mengadopsi standar *fail-secure* dari sisi luar dan *panic-exit* dari sisi dalam (pintu selalu dapat dibuka dari dalam untuk evakuasi darurat).

---

## 2. Prosedur Perbaikan & Pemenuhan Service Level Agreement (SLA)

| Kategori Kerusakan | Contoh Masalah | SLA Penyelesaian | Tindakan Rekayasa |
| :--- | :--- | :--- | :--- |
| **Kritikal (Critical)** | AC mati total, pipa bocor, kunci smart lock macet | Maksimal 120 Menit | Teknisi langsung dikirim ke lokasi; kamar di-out-of-order-kan jika perbaikan > 2 jam. |
| **Mayor (Major)** | Kulkas minibar tidak dingin, air panas tidak mengalir | Maksimal 180 Menit | Komponen diganti dari stok gudang pemeliharaan lantai. |
| **Minor (Minor)** | Bohlam lampu meja putus, hanger pakaian rusak | Maksimal 240 Menit | Perbaikan dilakukan saat kamar dalam keadaan kosong atau saat tamu keluar. |
