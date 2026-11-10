# ADR-0037: Persistent Consumer Inbox & Restart-Resilient Idempotency (W8-D37)

## Status
Diterima (Accepted)

## Konteks
Sebelum Fase R8, idempotensi pemrosesan event di `SpineConsumerRegistry` mengandalkan array statis dalam memori PHP (`protected static array $processedEvents`). Struktur ini memiliki kelemahan kritis: setiap kali proses pekerja Artisan berhenti, restart, atau crash, memori statis terhapus. Akibatnya, replay event atau re-dispatch outbox berpotensi mengeksekusi efek samping (posting ledger, pengiriman notifikasi, mutasi database) lebih dari satu kali, merusak integritas data sistem.

## Keputusan
1. **Pola Persistent Consumer Inbox (`core_consumer_inbox`)**:
   Dibuat tabel database persisten `core_consumer_inbox` dengan kunci unik komposit `(consumer_group, message_id)`.
   Kolom meliputi:
   - `id`: Primary key bigint unsigned.
   - `consumer_group`: String identitas grup konsumen (mis. `logistics_worker`, `accounting_sync`).
   - `message_id`: String/UUID pengenal unik event atau pesan outbox.
   - `handler_class`: Nama kelas handler yang memproses event.
   - `processed_at`: Timestamp saat event selesai dieksekusi.
   - `execution_time_ms`: Durasi eksekusi handler dalam milidetik.
   - `response_hash`: Hash SHA-256 dari keluaran handler untuk verifikasi idempotensi.

2. **Kontrak `ConsumerInbox` & Metode `claim()` Atomik**:
   Dibuat kontrak antarmuka `Modules\Core\Contracts\ConsumerInbox`:
   ```php
   namespace Modules\Core\Contracts;

   interface ConsumerInbox
   {
       public function claim(string $group, string $messageId, ?string $handler = null): bool;
       public function hasProcessed(string $group, string $messageId): bool;
       public function recordSuccess(string $group, string $messageId, ?string $responseHash = null, int $durationMs = 0): void;
   }
   ```
   Metode `claim()` dieksekusi di dalam transaksi database yang sama dengan eksekusi handler konsumen:
   - Jika `(group, message_id)` belum ada di `core_consumer_inbox`, claim berhasil (`true`).
   - Jika sudah ada (unique constraint violation), claim gagal (`false`), menandakan pesan telah diproses sebelumnya.

3. **Peran Cache In-Memory `SpineConsumerRegistry::$processedEvents`**:
   Cache memori in-memory pada `SpineConsumerRegistry` diturunkan statusnya menjadi L1 Fast Memory Cache opsional untuk efisiensi mikro dalam batch yang sama, tetapi **bukan** lagi penjaga idempotensi utama. Penjaga kebenaran mutlak adalah tabel database `core_consumer_inbox`.

## Konsekuensi
- Idempotensi konsumen terjamin tahan terhadap restart, crash, maupun eksekusi berulang dari replay stream.
- Pengiriman pesan duplikat secara deterministik ditolak tanpa menimbulkan efek ganda pada domain bisnis.

