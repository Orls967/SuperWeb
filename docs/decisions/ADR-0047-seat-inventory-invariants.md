# ADR-0047: Invarian Inventori Kursi, Hold Berumur Pendek, dan Tarif Immutable (G7-D47)

## Status
Diterima (Accepted) — 2026-11-08

## Konteks
Penjualan tiket penerbangan komersial membutuhkan konsistensi konkurensi tingkat tinggi untuk mencegah double booking atas kursi fisik yang sama, sambil mendukung reservasi sementara (seat hold) saat calon penumpang menyelesaikan proses verifikasi KYC dan pembayaran eksternal.

## Keputusan
1. **Satu Sumber Kebenaran:** Tabel `avi_seat_inventories` dengan foreign key ganda `(flight_id, cabin)` bertindak sebagai sumber kebenaran tunggal kapasitas dan ketersediaan kursi. Modifikasi baris dilakukan dengan pessimistic locking (`SELECT ... FOR UPDATE`).
2. **Hold Berumur Pendek:** Reservasi sementara dicatat pada `avi_seat_holds` dengan Time-To-Live (TTL) berbasis parameter berversi (nilai default fixture: 15 menit). Hold yang kedaluwarsa dilepas otomatis oleh background worker atau saat alokasi baru diminta.
3. **Kebijakan Oversell Eksplisit:** Penjualan melebihi kapasitas fisik pesawat dilarang, kecuali terdapat parameter kebijakan oversell eksplisit (dalam basis poin, default 0 bps).
4. **Tarif Immutable:** Penawaran harga tiket (fare quote) bersifat immutable setelah diterbitkan (mencakup rincian base fare, tax, currency, dan masa berlaku). Perubahan harga dinamis hanya berlaku untuk penawaran baru.
5. **Tiket Hanya Pasca Bayar:** Tiket penumpang berstatus `issued` dengan nomor unik PNR hanya boleh diterbitkan setelah konfirmasi pembayaran dari gateway berhasil diverifikasi.

## Konsekuensi
Menjamin ketersediaan kursi selalu deterministik dan menghilangkan risiko kerugian akibat kegagalan sinkronisasi inventori.
