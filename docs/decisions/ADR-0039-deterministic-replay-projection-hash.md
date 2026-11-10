# ADR-0039: Deterministic Event Replay & Canonical Projection Hash (W8-D39)

## Status
Diterima (Accepted)

## Konteks
Fitur pemutaran ulang (replay) event stream dari offset tertentu sangat krusial untuk pemulihan bencana (*disaster recovery*), perbaikan bug retroaktif, pengisian ulang proyeksi (read-model rehydration), dan audit kepatuhan. Namun, pemutaran ulang tanpa pembatasan berisiko menduplikasi pengiriman efek keluar sistem (seperti webhook, email, notifikasi, transaksi payment gateway) serta menghasilkan proyeksi status yang tidak deterministik jika reducer dipengaruhi faktor non-deterministik.

## Keputusan
1. **Replay Terbatas & Aman (Safe Replay Semantics)**:
   Perintah `spine:replay {group} --from=<offset> --to=<offset>` hanya membangun ulang keadaan proyeksi (*read-model projection*) dan menguji pemrosesan handler melalui mekanisme `ConsumerInbox`.
   Replay stream **dilarang mengirim ulang efek samping eksternal** ke luar sistem.

2. **Dukungan Reducer Deterministik**:
   Setiap modul atau konsumen mendaftarkan fungsi *projection reducer* murni tanpa efek samping eksternal. Reducer menerima status akumulasi saat ini dan payload event, mengembalikan status baru.

3. **Verifikasi Hash Proyeksi Kanonikal SHA-256**:
   Untuk membuktikan determinisme absolut pemutaran ulang:
   - Status akhir hasil proyeksi diserialisasi ke dalam bentuk JSON kanonikal (seluruh kunci diurutkan secara leksikografis `ksort()`).
   - Dihitung hash SHA-256 dari JSON kanonikal tersebut:
     $$h = \text{hash}('sha256', \text{canonicalJson}(state))$$
   - Dua kali pemutaran ulang stream event yang sama dari offset awal yang sama **wajib menghasilkan nilai hash proyeksi yang identik** ($h_1 == h_2$).

4. **Kompensasi & Penanganan DLQ pada Replay**:
   Pesan yang diputar ulang dari antrean Dead-Letter Queue (DLQ) dieksekusi melalui transaksi terisolasi yang memperbarui status delivery dari `replayed` menuju `delivered` atau `retrying`.

## Konsekuensi
- Menjamin rekonstruksi data historis yang 100% konsisten dan dapat diverifikasi oleh auditor independen.
- Menghilangkan risiko duplikasi transaksi eksternal selama proses investigasi atau perbaikan insiden.

