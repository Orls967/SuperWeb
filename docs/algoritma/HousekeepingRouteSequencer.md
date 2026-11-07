# Algoritma H3: Housekeeping Route Sequencer

## 1. Masalah Bisnis
Housekeeping adalah operasional padat karya dengan kendala waktu kritis (kamar harus siap sebelum jam check-in pukul 14:00). Tugas harus dialokasikan secara adil berdasarkan poin kredit kesulitan kamar (mis. kamar checkout berbobot 30 kredit, stayover 20 kredit, suite/VIP 40 kredit) tanpa melampaui kapasitas kerja harian petugas (240 kredit).

## 2. Aturan Urutan & Penugasan Deterministik
1. **Prioritas Kamar:**
   - Tingkat prioritas: VIP = 2, Prioritas Kedatangan (Arrival) = 1, Kamar Reguler = 0.
   - Diurutkan: Prioritas menurun $\to$ Kredit poin menurun $\to$ Nomor kamar menaik.
2. **Alokasi Petugas (Load Balancing):**
   - Kamar diberikan berurutan kepada petugas dengan beban kredit terendah saat ini.
   - Tie-break: Nama petugas berdasarkan urutan alfabetis menaik.
   - Batas kredit: Petugas tidak boleh menerima kamar jika akumulasi beban melampaui limit kredit (240). Kamar berlebih masuk ke antrean tunda (*unassigned queue*).
3. **Penyusunan Rute Pembersihan:**
   - Bagi tiap petugas, kamar dibersihkan dengan urutan: Kamar prioritas/VIP terlebih dahulu, disusul kamar non-prioritas berdasarkan nomor kamar menaik untuk meminimalkan waktu transit antar lantai/lorong.

## 3. Vektor Uji Prompt
- Petugas: A dan B, Batas kredit 240
- Kamar:
  - 101 co 30, 102 so 20, 103 co 30 P, 201 so 20, 202 co 30, 203 VIP 40 P, 204 so 20, 301 co 30, 302 so 20
- Hasil:
  - Petugas A: Alokasi $\{203, 202, 102, 201, 302\}$, Beban: 130, Rute: $[203, 102, 201, 202, 302]$
  - Petugas B: Alokasi $\{103, 101, 301, 204\}$, Beban: 110, Rute: $[103, 101, 204, 301]$
