<?php

declare(strict_types=1);

namespace Modules\Procurement\Application\Actions;

use DateTimeInterface;
use InvalidArgumentException;
use Modules\Procurement\Domain\Models\Requisition;
use Modules\Procurement\Domain\Models\Rfq;
use Modules\Procurement\Domain\Models\RfqInvitation;
use Modules\Shared\Application\BaseAction;

final class ConvertRequisitionToRfqAction extends BaseAction
{
    /**
     * Converts approved requisition into a competitive RFQ with supplier invitations.
     *
     * @param  list<string>  $invitedSupplierIds
     */
    public function execute(
        Requisition $requisition,
        string $title,
        DateTimeInterface $opensAt,
        DateTimeInterface $closesAt,
        array $invitedSupplierIds = [],
        string $terms = 'Net 30 days FOB Destination'
    ): Rfq {
        if ($closesAt <= $opensAt) {
            throw new InvalidArgumentException('Tenggat penutupan RFQ harus lebih besar dari waktu pembukaan.');
        }

        return $this->transaction(function () use ($requisition, $title, $opensAt, $closesAt, $invitedSupplierIds, $terms) {
            $rfqNumber = 'RFQ-'.date('Ymd').'-'.strtoupper(substr(md5((string) mt_rand()), 0, 6));

            $rfq = Rfq::create([
                'number' => $rfqNumber,
                'requisition_id' => $requisition->id,
                'title' => $title,
                'status' => 'issued',
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'terms' => $terms,
                'created_by_user_id' => auth()->id() ?? 1,
            ]);

            foreach ($invitedSupplierIds as $supplierId) {
                RfqInvitation::create([
                    'rfq_id' => $rfq->id,
                    'supplier_id' => $supplierId,
                    'invited_at' => now(),
                    'status' => 'pending',
                ]);
            }

            $requisition->update(['status' => 'rfq_created']);

            $this->audit('procurement.requisition_converted_to_rfq', $rfq, null, [
                'requisition_id' => $requisition->id,
                'invited_count' => count($invitedSupplierIds),
            ]);

            return $rfq->load('invitations');
        });
    }
}
