<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\ShiftStatus;
use Modules\Resto\Domain\Exceptions\ShiftAlreadyOpenException;
use Modules\Resto\Domain\Models\Shift;

class OpenShiftAction
{
    public function handle(int $cashierId, int $outletId, int $openingFloat, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($cashierId, $outletId, $openingFloat, $note) {
            $existing = Shift::where('cashier_id', $cashierId)
                ->where('status', ShiftStatus::OPEN)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new ShiftAlreadyOpenException("Kasir sudah memiliki shift aktif (#{$existing->id}) pada outlet tersebut.");
            }

            return Shift::create([
                'uuid' => (string) Str::uuid(),
                'outlet_id' => $outletId,
                'cashier_id' => $cashierId,
                'opened_at' => now(),
                'opening_float' => $openingFloat,
                'expected_cash' => $openingFloat,
                'counted_cash' => null,
                'variance' => 0,
                'status' => ShiftStatus::OPEN,
                'note' => $note,
            ]);
        });
    }
}
