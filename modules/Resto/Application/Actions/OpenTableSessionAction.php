<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\OrderChannel;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Enums\SessionStatus;
use Modules\Resto\Domain\Enums\TableStatus;
use Modules\Resto\Domain\Exceptions\TableOccupiedException;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\RestoTable;
use Modules\Resto\Domain\Models\TableSession;

class OpenTableSessionAction
{
    public function handle(int $tableId, int $guestCount, int $openedBy, ?int $customerId = null, ?string $guestName = null): TableSession
    {
        return DB::transaction(function () use ($tableId, $guestCount, $openedBy, $customerId, $guestName) {
            $table = RestoTable::where('id', $tableId)->lockForUpdate()->firstOrFail();

            if ($table->status !== TableStatus::AVAILABLE) {
                throw new TableOccupiedException("Meja {$table->code} sedang tidak tersedia (status: {$table->status->value}).");
            }

            $session = TableSession::create([
                'uuid' => (string) Str::uuid(),
                'outlet_id' => $table->outlet_id,
                'table_id' => $table->id,
                'opened_by' => $openedBy,
                'guest_count' => max(1, $guestCount),
                'opened_at' => now(),
                'status' => SessionStatus::OPEN,
            ]);

            $table->status = TableStatus::OCCUPIED;
            $table->save();

            // Create initial order for this session
            $outletCode = $table->outlet?->code ?: "OUT-{$table->outlet_id}";
            $dateStr = date('Ymd');
            $randomCode = strtoupper(Str::random(4));
            $orderNumber = "RSR-{$outletCode}-{$dateStr}-{$randomCode}";

            Order::create([
                'uuid' => (string) Str::uuid(),
                'outlet_id' => $table->outlet_id,
                'number' => $orderNumber,
                'table_session_id' => $session->id,
                'channel' => OrderChannel::DINE_IN,
                'customer_id' => $customerId,
                'guest_name' => $guestName ?: "Meja {$table->code} ({$guestCount} pax)",
                'subtotal' => 0,
                'discount' => 0,
                'service_charge' => 0,
                'tax_pb1' => 0,
                'rounding' => 0,
                'grand_total' => 0,
                'payment_method' => null,
                'status' => OrderStatus::OPEN,
                'idempotency_key' => "pos_order_{$session->id}_".Str::uuid(),
                'client_created_at' => now(),
            ]);

            return $session->load(['table', 'orders', 'openedBy']);
        });
    }
}
