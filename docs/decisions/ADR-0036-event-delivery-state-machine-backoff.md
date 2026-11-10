# ADR-0036: Event Delivery State Machine & Deterministic Backoff (W8-D36)

## Status
Diterima (Accepted)

## Konteks
Dalam arsitektur perpesanan event terdistribusi skala enterprise (Universal Event Spine), pengiriman event ke berbagai grup konsumen memerlukan manajemen siklus hidup pesan yang jelas, deterministik, dan dapat diaudit secara mendalam. Mekanisme retry konvensional seringkali mengandalkan fungsi acak non-deterministik (`rand()`) atau jeda statis yang rentan terhadap badai thundering herd saat kegagalan serentak terjadi. Selain itu, ketiadaan status pengiriman yang eksplisit menyulitkan pelacakan apakah pesan sedang dalam proses pengiriman, dijadwalkan ulang, mengalami kegagalan permanen, atau telah dibuang.

## Keputusan
1. **State Machine EventDelivery 7 Status**:
   Siklus hidup pengiriman event direpresentasikan melalui 7 status hukum yang terdokumentasi:
   - `pending`: Event siap diproses oleh grup konsumen.
   - `dispatched`: Event telah diambil oleh worker konsumen dan sedang dieksekusi.
   - `delivered`: Event sukses diproses dan claim inbox tercatat secara permanen (status akhir sukses).
   - `retrying`: Handler mengalami kegagalan sementara dan dijadwalkan untuk percobaan ulang.
   - `dead_lettered`: Event melebihi batas maksimum percobaan (default 6 kali) atau mengalami kegagalan fatal.
   - `replayed`: Event dari status `dead_lettered` dipicu ulang oleh operator berwenang (`spine_operator`).
   - `discarded`: Event dari status `dead_lettered` dihentikan secara permanen dengan alasan audit (status akhir pembuangan).

2. **Aturan Transisi & Guard Otorisasi**:
   - `pending` -> `dispatched`
   - `dispatched` -> `delivered`
   - `dispatched` -> `retrying` (gagal sementara; jeda dihitung oleh Algoritma A5)
   - `retrying` -> `dispatched` (ketika waktu jeda telah terlampaui)
   - `retrying` -> `dead_lettered` (ketika `attempts >= max_attempts`)
   - `dispatched` -> `dead_lettered` (kegagalan fatal langsung atau kegagalan poison 3 kali beruntun)
   - `dead_lettered` -> `replayed` (guard: role `spine_operator` dan pencatatan alasan operasional)
   - `replayed` -> `delivered` / `retrying`
   - `dead_lettered` -> `discarded` (guard: alasan pembuangan wajib; batch > 20 memerlukan izin `ApprovalEngine`)

3. **Algoritma A5 DeterministicBackoff**:
   Jeda retry dihitung secara deterministik murni bilangan bulat tanpa `rand()` atau panggilan `sleep()`:
   - Modulus MINSTD $m = 2^{31} - 1 = 2.147.483.647$, Multiplier $a = 48.271$.
   - $\text{mentah} = \min(\text{batas}, \text{dasar} \times 2^{n - 1})$ dengan dasar 30 detik dan batas 3.600 detik.
   - $\text{seed} = ((\text{idPesan} \bmod m) \times (n + 1)) \bmod m$; bila 0 diganti 1.
   - $x = (\text{seed} \times 48.271) \bmod m$.
   - $\text{jitter} = x \bmod \left(\lfloor \text{mentah} / 4 \rfloor + 1\right)$.
   - $\text{jeda} = \text{mentah} + \text{jitter}$.

4. **Jejak Audit Append-Only**:
   Setiap transisi status mencatat riwayat permanen di tabel pengiriman event memuat metadata `from_status`, `to_status`, `actor_id`, `reason`, dan `timestamp`.

## Konsekuensi
- Siklus pengiriman pesan menjadi sepenuhnya terlacak, dapat diulang, dan tidak menimbulkan thundering herd.
- Kegagalan sementara tertangani secara otomatis dengan jeda eksponensial yang terprediksi.

