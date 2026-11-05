<?php

declare(strict_types=1);

namespace Modules\ProServices\Ledger;

use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

final class ProServicesAccounts
{
    public const REVENUE = 'psv:revenue:IDR';

    public static function contractAsset(int|string $projectId): string
    {
        $code = "psv:contract_asset:{$projectId}:IDR";
        self::ensureAccountExists($code, "Project {$projectId} Contract Asset", AccountKind::ASSET);

        return $code;
    }

    public static function contractLiability(int|string $projectId): string
    {
        $code = "psv:contract_liability:{$projectId}:IDR";
        self::ensureAccountExists($code, "Project {$projectId} Contract Liability", AccountKind::LIABILITY);

        return $code;
    }

    public static function clientReceivable(int|string $partyId): string
    {
        $code = "ar:psv:client:{$partyId}:IDR";
        self::ensureAccountExists($code, "Client {$partyId} Receivable", AccountKind::ASSET);

        return $code;
    }

    public static function definitions(): array
    {
        return [
            [
                'code' => self::REVENUE,
                'name' => 'Professional Services Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'psv:contract_asset:1:IDR',
                'name' => 'Project 1 Contract Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'psv:contract_liability:1:IDR',
                'name' => 'Project 1 Contract Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ar:psv:client:101:IDR',
                'name' => 'Client 101 Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }

    private static function ensureAccountExists(string $code, string $name, AccountKind $kind): void
    {
        if (class_exists(LedgerAccount::class)) {
            $existing = LedgerAccount::query()->where('code', $code)->first();
            if (! $existing) {
                LedgerAccount::query()->create([
                    'code' => $code,
                    'name' => $name,
                    'kind' => $kind,
                    'asset_code' => 'IDR',
                    'allow_negative' => true,
                ]);
            }
        }
    }
}
