# Algoritma H4: HVAC Setback Controller

## 1. Masalah Bisnis
Sistem pendingin udara (AC) adalah konsumen energi terbesar di hotel (mencapai 60–70% tagihan listrik). Ketika tamu meninggalkan kamar untuk berwisata atau menghadiri rapat, menyalakan AC pada suhu dingin penuh ($20^\circ\text{C}$) memboroskan energi. Sebaliknya, mematikan AC sepenuhnya membuat kamar menjadi lembap dan panas saat tamu kembali.

## 2. Logika Setback & Penghematan Energi
- Sensor gerak IoT mendeteksi keberadaan tamu di kamar.
- Jika kamar berstatus terhuni (*occupied*) dan tidak ada gerakan selama $\ge 15$ menit:
  - Suhu dinaikkan $+3^\circ\text{C}$ (*comfort setback mode*).
  - Ketika tamu membuka pintu / gerakan terdeteksi, AC kembali ke suhu preferensi tamu.
- Jika kamar tidak terjual (*vacant*):
  - Mode hemat penuh diaktifkan sepanjang hari ($+5^\circ\text{C}$ atau deep eco).
- Perhitungan Penghematan Energi:
$$\text{Hemat Energi (Wh)} = \frac{\text{Menit Setback} \times \text{Daya AC (Watt)} \times \text{Faktor Hemat (bps)}}{60 \times 10.000}$$

## 3. Vektor Uji Prompt
- Tamu keluar pukul 10:00, setback aktif pukul 10:15, tamu kembali pukul 13:00.
- Durasi setback: 165 menit.
- Daya AC: $1.200 \text{ Watt}$, Faktor hemat: $3.000 \text{ bps} (30\%)$.
- Energi dihemat:
$$\text{Wh} = \frac{165 \times 1.200 \times 3.000}{600.000} = 990 \text{ Wh}$$
Angka ini dilaporkan secara otomatis ke modul ESG sebagai pengurangan jejak karbon per occupied-room-night.
