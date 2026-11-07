# Algoritma H5: Service Recovery Compensator

## 1. Masalah Bisnis
Gangguan fasilitas di kamar (AC rusak, air panas mati, kebocoran) menurunkan kepuasan tamu dan meningkatkan risiko ulasan buruk. Hotel memerlukan formula kompensasi terstandarisasi yang adil dan transparan tanpa perlu persetujuan manual yang lambat.

## 2. Aturan Kompensasi Berdasarkan SLA Keterlambatan
Kelebihan waktu perbaikan melebihi Service Level Agreement (SLA):
- $\le 2 \text{ jam} \to 10\%$ dari tarif malam kamar (dalam bps: 1.000 bps).
- $> 2 \text{ jam}$ hingga $6 \text{ jam} \to 25\%$ dari tarif malam kamar (dalam bps: 2.500 bps).
- $> 6 \text{ jam} \to 50\%$ dari tarif malam kamar (dalam bps: 5.000 bps) ditambah 1 sertifikat malam menginap gratis (*Free Night Certificate*).
- Plafon: Kompensasi uang maksimal 1 tarif malam kamar.
- Akuntansi: Kompensasi dikreditkan ke folio tamu sebagai *contra-revenue* pada akun `htl:service_recovery_contra_revenue:IDR`.

## 3. Vektor Uji Prompt (Tarif Malam Rp 1.500.000)
- Terlambat 1 jam: $\text{Rp } 150.000$ (10%), tanpa malam gratis.
- Terlambat 4 jam: $\text{Rp } 375.000$ (25%), tanpa malam gratis.
- Terlambat 10 jam: $\text{Rp } 750.000$ (50%) + sertifikat menginap gratis.
