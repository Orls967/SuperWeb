# Matriks Kepekaan Pasar & Panduan Respons Strategis Manajemen Hotel (Fase 91)

Dokumen ini mendokumentasikan pemodelan elastisitas permintaan, respons terhadap fluktuasi makroekonomi, dan skenario adaptasi operasional hotel.

---

## 1. Pemodelan Kepekaan Terhadap Guncangan Permintaan

| Skenario Guncangan | Variabel Terpengaruh | Dampak Finansial Langsung | Respons Otomatis Sistem | Tindakan Manual Manajer |
| :--- | :--- | :--- | :--- | :--- |
| **Peringatan Perjalanan (Travel Advisory) Negatif** | Kunjungan Wisman (-40%) | Penurunan drastis okupansi dan RevPAR properti resort di Bali/Lombok. | Algoritma BAR mengaktifkan tier diskon okupansi rendah (< 50% memicu faktor 0.90x). | Manajer meluncurkan paket promosi *staycation* domestik dan promosi F&B. |
| **Penyelenggaraan KTT Internasional / Konser Musik Akbar** | Lonjakan Permintaan (+150%) | Kamar hotel di seluruh kota terisi penuh (okupansi 100%). | Faktor event H2 (+15%) aktif; kuota overbooking (H1) diperketat menjadi 1% untuk mencegah risiko walk berlebih. | Front desk berkoordinasi dengan properti tetangga untuk mengamankan kamar cadangan darurat. |
| **Kenaikan Tarif Listrik PLN (Tarif Tenaga Listrik Bisnis)** | Biaya Utilitas (+20%) | Peningkatan beban pengeluaran operasional pendingin udara per kamar. | Algoritma H4 mempercepat waktu transisi ke mode HVAC Setback menjadi 10 menit pasca kekosongan kamar. | Tim Engineering memeriksa isolasi termal jendela dan merawat kompresor AC. |
| **Perubahan Perda Tarif Pajak PBJT Daerah** | Tarif PBJT (Misal: dari 10% menjadi 8%) | Penyesuaian perhitungan tagihan bruto konsumen. | Parameter berversi diupdate pada tabel faktor eksternal dengan tanggal efektif baru; tagihan baru menggunakan tarif baru secara otomatis. | Bagian Akuntansi memastikan rekonsiliasi setoran pajak daerah cocok dengan laporan bulanan. |
