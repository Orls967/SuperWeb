<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Logistics\Contracts\DemurrageDetentionQuote;
use Modules\Port\Domain\Models\TerminalBill;
use Modules\Port\Domain\Models\VesselCall;
use Modules\Port\Ledger\PortAccounts;
use Modules\Port\Ledger\PortTransactionTypes;

class GenerateTerminalBillAction
{
    public function __construct(
        private readonly DemurrageDetentionQuote $dndQuote,
        private readonly ?LedgerService $ledgerService = null
    ) {}

    /**
     * Menerbitkan tagihan terminal terintegrasi untuk sebuah kunjungan kapal.
     */
    public function execute(
        int $vesselCallId,
        int $containerCount = 1,
        int $dwellDays = 7
    ): TerminalBill {
        return DB::transaction(function () use ($vesselCallId, $containerCount, $dwellDays) {
            $call = VesselCall::findOrFail($vesselCallId);

            // Invarian idempotensi: cegah double billing pada kunjungan kapal yang sama
            $existingBill = TerminalBill::where('vessel_call_id', $call->id)
                ->whereIn('status', ['ISSUED', 'PAID'])
                ->first();

            if ($existingBill !== null) {
                throw new DomainException("Tagihan terminal untuk kunjungan kapal {$call->call_number} sudah pernah diterbitkan ({$existingBill->bill_number}).");
            }

            // Komponen tarif terminal
            $thcRatePerTeu = 1_500_000;
            $thcAmount = $thcRatePerTeu * max(1, $containerCount);

            $dockageHourlyRate = 50_000;
            $dockageHours = max(1, $call->duration_hours);
            $dockageAmount = $dockageHourlyRate * $dockageHours;

            $storageDailyRate = 100_000;
            $storageAmount = $storageDailyRate * max(0, $dwellDays);

            // Hitung D&D via Kontrak K5
            $from = CarbonImmutable::now()->subDays($dwellDays);
            $to = CarbonImmutable::now();
            $dndQuoteResult = $this->dndQuote->quote($call->id, $from, $to);
            $dndAmount = $dndQuoteResult['demurrage_idr'] + $dndQuoteResult['detention_idr'];

            $totalAmount = $thcAmount + $dockageAmount + $storageAmount + $dndAmount;

            $billNumber = 'TB-'.strtoupper(bin2hex(random_bytes(4)));

            $bill = TerminalBill::create([
                'bill_number' => $billNumber,
                'vessel_call_id' => $call->id,
                'line_party_id' => $call->line_party_id,
                'amount_idr' => $totalAmount,
                'thc_amount_idr' => $thcAmount,
                'dockage_amount_idr' => $dockageAmount,
                'storage_amount_idr' => $storageAmount,
                'dnd_amount_idr' => $dndAmount,
                'status' => 'ISSUED',
            ]);

            // Double-entry posting ke Buku Besar perbankan jika tabel bank_ledger_accounts tersedia
            if (Schema::hasTable('bank_ledger_accounts')) {
                $linePartyCode = PortAccounts::lineReceivableCode($call->line_party_id);

                // Pastikan akun-akun ledger terdaftar
                LedgerAccount::firstOrCreate(
                    ['code' => $linePartyCode],
                    [
                        'name' => "AR Shipping Line {$call->line_party_id}",
                        'kind' => AccountKind::ASSET,
                        'asset_code' => 'IDR',
                        'allow_negative' => true,
                    ]
                );

                foreach (PortAccounts::definitions() as $def) {
                    LedgerAccount::firstOrCreate(
                        ['code' => $def['code']],
                        [
                            'name' => $def['name'],
                            'kind' => $def['kind'],
                            'asset_code' => $def['asset_code'],
                            'allow_negative' => $def['allow_negative'],
                        ]
                    );
                }

                $ledger = $this->ledgerService ?? app(LedgerService::class);
                $posting = Posting::lines()->debit($linePartyCode, $totalAmount);

                if ($thcAmount > 0) {
                    $posting->credit('rev:prt:thc:IDR', $thcAmount);
                }
                if ($dockageAmount > 0) {
                    $posting->credit('rev:prt:dockage:IDR', $dockageAmount);
                }
                if ($storageAmount > 0) {
                    $posting->credit('rev:prt:storage:IDR', $storageAmount);
                }
                if ($dndAmount > 0) {
                    $posting->credit('prt:dnd_revenue:IDR', $dndAmount);
                }

                $journal = $ledger->post(new PostingDTO(
                    type: PortTransactionTypes::PORT_TERMINAL_BILLING->value,
                    description: "Port terminal billing untuk vessel call {$call->call_number}",
                    idempotencyKey: "prt:bill:{$bill->id}",
                    entries: $posting->toEntries('IDR'),
                    referenceType: 'PORT_TERMINAL_BILL',
                    referenceId: (string) $bill->id,
                ));

                $bill->update(['ledger_journal_id' => $journal->id]);
            }

            return $bill;
        });
    }
}
