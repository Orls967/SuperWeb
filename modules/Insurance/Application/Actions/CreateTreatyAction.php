<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Insurance\Application\Support\InsuranceNumbering;
use Modules\Insurance\Domain\Enums\TreatyStatus;
use Modules\Insurance\Domain\Enums\TreatyType;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;
use Modules\Insurance\Domain\Models\ReinsuranceTreatyPanel;

final class CreateTreatyAction
{
    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     type: TreatyType|string,
     *     line_category: string,
     *     inception_date: string,
     *     expiry_date: string,
     *     quota_share_bps?: int,
     *     surplus_retention_idr?: int,
     *     surplus_lines?: int,
     *     attachment_idr?: int,
     *     limit_idr?: int,
     *     reinstatement_rates_bps?: array<int>,
     *     layer_premium_idr?: int,
     *     commission_bps?: int,
     *     sl_attachment_lr_bps?: int,
     *     sl_limit_lr_bps?: int,
     *     panel: list<array{reinsurer_party_id: string|int, share_bps: int}>,
     * }  $payload
     */
    public function execute(array $payload, ?int $creatorId = null): ReinsuranceTreaty
    {
        $type = is_string($payload['type']) ? TreatyType::from($payload['type']) : $payload['type'];
        $panel = $payload['panel'] ?? [];

        if ($panel === []) {
            throw new InvalidArgumentException('Panel reinsurer minimal 1 pihak.');
        }

        $totalShare = array_sum(array_column($panel, 'share_bps'));
        if ($totalShare !== 10000) {
            throw new InvalidArgumentException("Total share panel harus 10.000 bps (100%), terhitung: {$totalShare} bps.");
        }

        return DB::transaction(function () use ($payload, $type, $panel, $creatorId) {
            $treatyCode = InsuranceNumbering::treaty($this->numbering);

            $treaty = ReinsuranceTreaty::create([
                'treaty_code' => $treatyCode,
                'name' => $payload['name'],
                'type' => $type,
                'line_category' => $payload['line_category'],
                'status' => TreatyStatus::Draft,
                'inception_date' => $payload['inception_date'],
                'expiry_date' => $payload['expiry_date'],
                'quota_share_bps' => (int) ($payload['quota_share_bps'] ?? 0),
                'surplus_retention_idr' => (int) ($payload['surplus_retention_idr'] ?? 0),
                'surplus_lines' => (int) ($payload['surplus_lines'] ?? 0),
                'attachment_idr' => (int) ($payload['attachment_idr'] ?? 0),
                'limit_idr' => (int) ($payload['limit_idr'] ?? 0),
                'reinstatement_rates_bps' => $payload['reinstatement_rates_bps'] ?? [],
                'layer_premium_idr' => (int) ($payload['layer_premium_idr'] ?? 0),
                'commission_bps' => (int) ($payload['commission_bps'] ?? 0),
                'sl_attachment_lr_bps' => (int) ($payload['sl_attachment_lr_bps'] ?? 0),
                'sl_limit_lr_bps' => (int) ($payload['sl_limit_lr_bps'] ?? 0),
                'created_by' => $creatorId,
            ]);

            foreach ($panel as $item) {
                ReinsuranceTreatyPanel::create([
                    'treaty_id' => $treaty->id,
                    'reinsurer_party_id' => (string) $item['reinsurer_party_id'],
                    'share_bps' => (int) $item['share_bps'],
                ]);
            }

            $treaty->history()->create([
                'from_status' => null,
                'to_status' => TreatyStatus::Draft->value,
                'reason' => 'Treaty baru dibuat dalam status Draf.',
                'actor_user_id' => $creatorId,
                'occurred_at' => $this->clock->now(),
            ]);

            return $treaty->load('panel');
        });
    }
}
