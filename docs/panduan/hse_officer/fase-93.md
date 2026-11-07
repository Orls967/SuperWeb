# Panduan Operasional Peran: HSE Officer (`hse_officer`) — Fase 93

## 1. Tanggung Jawab Utama
HSE Officer (Health, Safety, and Environment) bertanggung jawab memastikan operasi penambangan terbuka mematuhi standar keselamatan kerja pertambangan (Kepmen ESDM 1827 K/2018), memantau parameter keselamatan armada alat berat melalui telemetri sensor IoT, dan mengendalikan risiko kecelakaan kerja di pit penambangan dan jalur hauling.

## 2. Alur Tugas & Prosedur Operasional Harian
1. **Pemantauan Batas Kritis Suhu dan Tekanan Ban (TKPH)**:
   - Akses dasbor keselamatan armada di `/mining/equipment`.
   - Amati telemetri armada haul truck yang beroperasi pada rute berjarak tempuh jauh ($> 5\text{ km}$).
   - Parameter Kritis yang Dipantau:
     - **Suhu Ban**: Batas kritis adalah $95^\circ\text{C}$. Suhu melebihi ambang ini berisiko memicu ledakan ban radial (*tire blowout*).
     - **Tekanan Ban**: Rentang aman operasi adalah $90 - 130\text{ psi}$. Tekanan di bawah $90\text{ psi}$ menyebabkan flexing berlebih dan panas internal; tekanan di atas $130\text{ psi}$ meningkatkan risiko robek akibat benturan batu tajam.
     - **Nilai TKPH (Ton-Kilometer-Per-Hour)**: Nilai operasi TKPH tidak boleh melampaui rating ban ($400\text{ TKPH}$).
   - *Tindakan Mitigasi*: Bila sistem memicu alarm `ALERT_HOT_TIRE`, instruksikan dispatcher untuk mengarahkan unit ke area pendinginan (*cool-down bay*) minimal 30 menit.

2. **Evaluasi Kelayakan dan Keselamatan Jalan Hauling**:
   - Lakukan inspeksi visual dan pengukuran kekasaran permukaan jalan tambang (IRI - International Roughness Index).
   - Pastikan rolling resistance jalan tidak melebihi $3.0\%$. Jika jalan bergelombang (IRI $> 3.5$), jadwalkan perataan oleh Motor Grader.
   - Periksa konsentrasi debu jalan (PM10). Jika debu melebihi $150\text{ ppm}$, instruksikan penyiraman intensif oleh Water Truck untuk menjaga jarak pandang pengemudi minimal $100\text{ meter}$.

3. **Protokol Tanggap Cuaca Ekstrem (Curah Hujan Tinggi)**:
   - Jika intensitas hujan mencapai $> 20\text{ mm/jam}$ atau jarak pandang $< 50\text{ meter}$, keluarkan peringatan penghentian operasi hauling (*Stop Work Order*).
   - Pastikan seluruh armada diparkir di tempat aman dengan jarak antar-unit minimal $10\text{ meter}$ dan rem parkir aktif.
   - Koordinasikan dengan Mine Planner untuk menjalankan `RePlanShiftTargetsAction` guna memperhitungkan jam kerja hilang.

4. **Pengawasan Pengisian Bahan Bakar Lapangan**:
   - Pastikan proses pengisian solar oleh mobile fuel bowser mematuhi prosedur anti-statis (kabel grounding terpasang sebelum nozzle dibuka).
   - Periksa ketersediaan alat pemadam api ringan (APAR) tipe dry chemical powder $9\text{ kg}$ pada setiap unit fuel truck.

