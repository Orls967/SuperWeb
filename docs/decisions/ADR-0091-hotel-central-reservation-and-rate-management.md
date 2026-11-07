# ADR-0091: Arsitektur Central Reservation System, Overbooking Optimizer, Dynamic BAR dan Smart Room

**Status:** DITERIMA (Anchor: G7-D39, G7-D40, G7-D43, D91-1..D91-9)  
**Tanggal:** 2026-11-08  
**Penyusun:** Tim Rekayasa Perhotelan Superweb (G7-P6)  

## Konteks Bisnis
Operasional hotel skala enterprise memerlukan inventori terpusat bebas *double-booking*, optimasi overbooking probabilistik terukur, penetapan harga dinamis Best Available Rate (BAR), tata kelola rute housekeeping harian, integrasi IoT twin energi kamar, dan kepatuhan hukum imigrasi WNA (UU Keimigrasian APOA) serta pajak daerah PBJT.

## Keputusan Arsitektur
1. **Unit Inventori & Locking (G7-D40):** Inventori dikelola pada tingkat kombinasi tuple `(property_id, room_type_id, business_date)` dengan pessimistic lock per malam kamar.
2. **Optimasi Overbooking (H1):** Batas overbooking dihitung melalui algoritma `OverbookingLimitOptimizer` berbasis ekspektasi keuntungan bersih terhadap risiko biaya walk dan kompensasi tamu, dengan batas absolut kebijakan maksimum 3% kapasitas.
3. **Pemisahan Tarif (H2):** Formula Best Available Rate (BAR) mempertimbangkan okupansi dan lead time namun tunduk pada batas guardrail floor, ceiling, serta kekebalan kontrak korporat/grup yang tidak boleh tertimpa.
4. **Housekeeping Route Sequencer (H3):** Pembagian beban kerja petugas kebersihan kamar menggunakan pembagian kredit proporsional deterministik dengan prioritas VIP/arrival mendahului kamar checkout standar.
5. **Smart Room HVAC Setback (H4):** Integrasi sensor gerak/okupansi kamar mengaktifkan mode setback +3 °C saat kamar kosong terisi tamu $\ge 15$ menit, dan melaporkan kWh hemat energi ke modul ESG.
6. **Kompensasi Gangguan Pelayanan (H5):** Pelanggaran SLA kerusakan fasilitas mengompensasi tamu secara berjenjang ke folio sebagai contra-revenue.
7. **Kepatuhan APOA (UU 6/2011):** Tamu WNA check-in secara otomatis mendaftarkan antrean pelaporan imigrasi APOA berbasis hash paspor (blind index) dan batas waktu 24 jam dengan eskalasi GM bila terlambat.
