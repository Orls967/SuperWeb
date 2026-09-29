<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Exception;
use Illuminate\Support\Str;
use Modules\Banking\Application\Actions\VerifyPinAction;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Finance\Application\Services\LoanSimulator;
use Modules\Finance\Domain\Enums\InstallmentStatus;
use Modules\Finance\Domain\Enums\LoanStatus;
use Modules\Finance\Domain\Models\Installment;
use Modules\Finance\Domain\Models\Loan;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\OrderItem;
use Modules\Store\Domain\Models\Product;

/**
 * HODL-to-Drive: beli mobil dengan DP tunai + pembiayaan berjaminan kripto.
 * Kolateral dikunci, pinjaman dicairkan, dan order mobil langsung dibayar
 * dalam satu rangkaian transaksi atomik.
 */
class OpenLoanAction extends BaseAction
{
    /** Ongkos kirim/handling unit kendaraan, sama dengan alur checkout Store. */
    private const VEHICLE_HANDLING_FEE = 500_000;

    public function __construct(
        private readonly Ledger $ledger,
        private readonly PriceFeed $priceFeed,
        private readonly PaymentGateway $paymentGateway,
        private readonly InventoryService $inventoryService,
        private readonly VerifyPinAction $verifyPinAction,
        private readonly LoanSimulator $simulator,
    ) {}

    /**
     * @param  array{name: string, phone: string, address: string, city: string, postal_code: string}  $shippingAddress
     */
    public function execute(
        User $user,
        Product $product,
        int $downPayment,
        int $tenorMonths,
        string $collateralSymbol,
        array $shippingAddress,
        string $pin,
        ?string $idempotencyKey = null,
    ): Loan {
        if (! $product->is_car || $product->productable_type !== 'dex_car') {
            throw new Exception('Pembiayaan HODL-to-Drive hanya berlaku untuk unit mobil baru di Store.');
        }

        if (! $product->is_listed || $this->inventoryService->available($product->id) < 1) {
            throw new Exception('Unit mobil ini sedang tidak tersedia.');
        }

        $asset = CryptoAsset::where('symbol', strtoupper($collateralSymbol))->where('is_active', true)->first();
        if ($asset === null) {
            throw new Exception("Aset kolateral {$collateralSymbol} tidak tersedia.");
        }

        $grandTotal = (int) $product->price + self::VEHICLE_HANDLING_FEE;

        if ($downPayment < 0 || $downPayment >= $grandTotal) {
            throw new Exception('Uang muka harus lebih kecil dari total harga unit.');
        }

        $principal = $grandTotal - $downPayment;
        $schedule = $this->simulator->simulate($principal, $tenorMonths);

        $price = $this->priceFeed->currentPrice($asset->symbol);
        $requiredQty = Loan::requiredCollateralQty($principal, $price, (int) $asset->decimals);

        $holding = BigDecimal::of($user->walletAccount($asset->symbol)->cached_balance ?: '0');
        if ($holding->isLessThan($requiredQty)) {
            throw new Exception(sprintf(
                'Kolateral %s tidak mencukupi. Dibutuhkan %s %s (LTV maksimal %d%%), holding kamu %s %s.',
                $asset->symbol,
                $requiredQty->__toString(),
                $asset->symbol,
                (int) (Loan::MAX_LTV_AT_OPEN * 100),
                $holding->__toString(),
                $asset->symbol
            ));
        }

        $walletIdr = (int) $user->walletAccount('IDR')->cached_balance;
        if ($walletIdr < $downPayment) {
            $kurang = number_format($downPayment - $walletIdr, 0, ',', '.');
            throw new Exception("Saldo dompet kurang Rp {$kurang} untuk membayar uang muka.");
        }

        $this->verifyPinAction->execute($user, $pin);

        $key = $idempotencyKey ?? (string) Str::uuid();

        return $this->transaction(function () use (
            $user,
            $product,
            $asset,
            $price,
            $requiredQty,
            $principal,
            $downPayment,
            $tenorMonths,
            $grandTotal,
            $shippingAddress,
            $schedule,
            $key
        ) {
            // 1. Buat order unit mobil dan tahan stoknya
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'status' => OrderStatus::PENDING_PAYMENT,
                'subtotal' => (int) $product->price,
                'shipping_fee' => self::VEHICLE_HANDLING_FEE,
                'discount' => 0,
                'grand_total' => $grandTotal,
                'shipping_address' => $shippingAddress,
            ]);

            $movement = $this->inventoryService->reserve(
                $product->id,
                1,
                Order::class,
                $order->id,
                "Reservasi unit pembiayaan order {$order->number}",
                $user->id
            );

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'name_snapshot' => $product->name,
                'price_snapshot' => (int) $product->price,
                'qty' => 1,
                'line_total' => (int) $product->price,
                'reservation_id' => $movement->id,
            ]);

            // 2. Kunci kolateral & cairkan pinjaman dalam satu transaksi ledger
            $cryptoAccount = $user->walletAccount($asset->symbol);
            $idrAccount = $user->walletAccount('IDR');

            $this->ledger->post(new PostingDTO(
                type: TransactionType::LOAN_DISBURSEMENT->value,
                description: "Pencairan pembiayaan HODL-to-Drive untuk order {$order->number}",
                idempotencyKey: 'loan_open_'.$key,
                entries: [
                    PostingEntryDTO::forAccount($cryptoAccount->id, $asset->symbol, $requiredQty->negated()),
                    PostingEntryDTO::forCode("collateral:crypto:{$asset->symbol}", $asset->symbol, $requiredQty),
                    PostingEntryDTO::forCode('loan_receivable:IDR', 'IDR', BigDecimal::of((string) $principal)->negated()),
                    PostingEntryDTO::forAccount($idrAccount->id, 'IDR', (string) $principal),
                ],
                referenceType: 'store_order',
                referenceId: $order->id,
                meta: [
                    'principal' => $principal,
                    'collateral_asset' => $asset->symbol,
                    'collateral_qty' => $requiredQty->__toString(),
                    'collateral_price_idr' => $price->__toString(),
                ],
                createdBy: $user->id,
                postedAt: now(),
            ));

            // 3. Bayar order: DP dari saldo sendiri + dana pinjaman yang baru cair
            $this->paymentGateway->charge($order->fresh(), 'loan_order_'.$key);

            // 4. Catat pinjaman dan jadwal cicilannya
            $loan = Loan::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'principal' => $principal,
                'down_payment' => $downPayment,
                'interest_rate_annual' => $schedule['interest_rate_annual'],
                'tenor_months' => $tenorMonths,
                'collateral_asset_id' => $asset->id,
                'collateral_qty' => $requiredQty->__toString(),
                'ltv_at_open' => $this->simulator->ltvFor($principal, $requiredQty, $price),
                'outstanding_principal' => $principal,
                'status' => LoanStatus::Active,
                'opened_at' => now(),
            ]);

            foreach ($schedule['schedule'] as $row) {
                Installment::create([
                    'loan_id' => $loan->id,
                    'sequence' => $row['sequence'],
                    'due_date' => $row['due_date'],
                    'principal_part' => $row['principal_part'],
                    'interest_part' => $row['interest_part'],
                    'amount' => $row['amount'],
                    'status' => InstallmentStatus::Scheduled,
                ]);
            }

            return $loan->fresh(['installments', 'collateralAsset']);
        });
    }
}
