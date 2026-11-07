# Prosedur Pemulihan Bencana & Keberlanjutan Usaha Hotel (Disaster Recovery & Business Continuity)

Dokumen ini mendokumentasikan skenario darurat, prosedur penanganan bencana alam (gempa bumi, erupsi gunung berapi), kegagalan infrastruktur kelistrikan, dan pemulihan sistem teknologi informasi hotel.

---

## 1. Protokol Bencana Alam & Penutupan Jalur Akses (Force Majeure)

### 1.1 Penutupan Bandara Akibat Erupsi Gunung Berapi
Bila otoritas navigasi penerbangan (AirNav / Kemenhub) mengeluarkan NOTAM penutupan bandara akibat abu vulkanik:
1. **Aktivasi Kebijakan Force Majeure**:
   - Manajer Pendapatan (Revenue Manager) mengaktifkan parameter `travel_disruption_active` di sistem PMS.
   - Kebijakan pembatalan bebas denda (*cancellation fee waiver*) otomatis berlaku bagi seluruh tamu yang penerbangannya terdampak.
   - Kuota overbooking harian (H1) secara otomatis diturunkan ke angka 0 untuk mencegah penumpukan tamu stranded.
2. **Perpanjangan Menginap Darurat (Distressed Passenger Extension)**:
   - Tamu in-house yang tertahan di hotel diberikan tarif khusus darurat (*distressed passenger rate*) dengan diskon 30% dari tarif normal.
   - Folio tamu dapat dialihkan sementara ke penjamin maskapai penerbangan jika terdapat perjanjian akomodasi darurat.

---

## 2. Kegagalan Infrastruktur Listrik & Gateway IoT Smart Room

### 2.1 Prosedur Transisi Generator Darurat (Genset)
1. Genset otomatis menyala dalam kurun waktu 15 detik sejak padamnya listrik PLN.
2. Sistem pendingin udara (AC) secara otomatis dikunci pada mode efisiensi darurat (*emergency eco-mode*) dengan menaikkan setpoint temperatur ke 26 °C untuk meringankan beban genset.
3. Seluruh gateway smart lock turnstile berpindah ke mode *fail-safe* menggunakan cadangan daya baterai internal (UPS) yang mampu bertahan hingga 8 jam.

### 2.2 Prosedur Offline Door Unlock (Master Key Override)
- Jika gateway nirkabel IoT mengalami kegagalan transmisi data:
  - Petugas keamanan hotel dan Duty Manager menggunakan kunci fisik mekanik master (*high-security mechanical key*) untuk membuka pintu kamar tamu secara manual.
  - Setiap pembukaan pintu darurat wajib dicatat dalam buku jurnal keamanan fisik dan diaudit keesokan harinya.
