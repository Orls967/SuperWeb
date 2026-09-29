<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Finance\Application\Actions\AddCollateralAction;
use Modules\Finance\Application\Actions\OpenLoanAction;
use Modules\Finance\Application\Actions\PayInstallmentAction;
use Modules\Finance\Application\Services\LoanSimulator;
use Modules\Finance\Domain\Models\Loan;
use Modules\Finance\Http\Requests\OpenLoanRequest;
use Modules\Store\Domain\Models\Product;

class LoanController extends Controller
{
    public function __construct(
        private readonly LoanSimulator $simulator,
        private readonly PriceFeed $priceFeed,
        private readonly OpenLoanAction $openLoan,
        private readonly PayInstallmentAction $payInstallment,
        private readonly AddCollateralAction $addCollateral,
    ) {}

    public function index(Request $request): View
    {
        $loans = Loan::where('user_id', $request->user()->id)
            ->with(['collateralAsset', 'installments'])
            ->latest()
            ->get();

        $prices = [];
        foreach ($loans as $loan) {
            $symbol = $loan->collateralAsset->symbol;
            $prices[$symbol] ??= $this->priceFeed->currentPrice($symbol);
        }

        return view('finance::loans.index', [
            'loans' => $loans,
            'prices' => $prices,
        ]);
    }

    public function show(Request $request, Loan $loan): View
    {
        $user = $request->user();
        if ((int) $loan->user_id !== (int) $user->id && ! $user->isAdmin()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $loan->load(['collateralAsset', 'installments', 'user', 'order']);
        $price = $this->priceFeed->currentPrice($loan->collateralAsset->symbol);

        return view('finance::loans.show', [
            'loan' => $loan,
            'price' => $price,
            'ltv' => $loan->currentLtv($price),
            'collateralValue' => $loan->collateralValue($price),
            'walletBalance' => (int) $user->walletAccount('IDR')->cached_balance,
            'cryptoHolding' => (string) $user->walletAccount($loan->collateralAsset->symbol)->cached_balance,
        ]);
    }

    /**
     * Halaman simulator pembiayaan untuk sebuah unit mobil di Store.
     */
    public function simulate(Request $request, Product $product): View|RedirectResponse
    {
        if (! $product->is_car || $product->productable_type !== 'dex_car') {
            return redirect()->route('store.catalog.index')
                ->with('error', 'Pembiayaan HODL-to-Drive hanya tersedia untuk unit mobil baru.');
        }

        $user = $request->user();
        $assets = CryptoAsset::where('is_active', true)->orderBy('symbol')->get();

        $holdings = [];
        $prices = [];
        foreach ($assets as $asset) {
            $holdings[$asset->symbol] = (string) $user->walletAccount($asset->symbol)->cached_balance;
            $prices[$asset->symbol] = $this->priceFeed->currentPrice($asset->symbol)->__toString();
        }

        return view('finance::loans.simulate', [
            'product' => $product,
            'assets' => $assets,
            'holdings' => $holdings,
            'prices' => $prices,
            'tenors' => LoanSimulator::ALLOWED_TENORS,
            'handlingFee' => 500_000,
            'maxLtv' => Loan::MAX_LTV_AT_OPEN,
            'annualRate' => 0.08,
            'walletBalance' => (int) $user->walletAccount('IDR')->cached_balance,
        ]);
    }

    /**
     * Endpoint JSON untuk simulator Alpine: jadwal cicilan dan kebutuhan kolateral.
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'principal' => 'required|integer|min:1',
            'tenor_months' => 'required|integer',
            'symbol' => 'required|string|max:10',
        ]);

        try {
            $simulation = $this->simulator->simulate(
                (int) $validated['principal'],
                (int) $validated['tenor_months']
            );

            $asset = CryptoAsset::where('symbol', strtoupper($validated['symbol']))->firstOrFail();
            $price = $this->priceFeed->currentPrice($asset->symbol);
            $required = $this->simulator->requiredCollateral(
                (int) $validated['principal'],
                $price,
                (int) $asset->decimals
            );

            return response()->json([
                'success' => true,
                'simulation' => $simulation,
                'collateral' => [
                    'symbol' => $asset->symbol,
                    'price_idr' => $price->__toString(),
                    'required_qty' => $required->__toString(),
                    'required_value_idr' => $required->multipliedBy($price)->toScale(0)->__toString(),
                    'holding' => (string) $request->user()->walletAccount($asset->symbol)->cached_balance,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function store(OpenLoanRequest $request, Product $product): RedirectResponse
    {
        try {
            $loan = $this->openLoan->execute(
                user: $request->user(),
                product: $product,
                downPayment: $request->integer('down_payment'),
                tenorMonths: $request->integer('tenor_months'),
                collateralSymbol: (string) $request->input('collateral_symbol'),
                shippingAddress: [
                    'name' => $request->input('recipient_name'),
                    'phone' => $request->input('phone'),
                    'address' => $request->input('address'),
                    'city' => $request->input('city'),
                    'postal_code' => $request->input('postal_code'),
                ],
                pin: (string) $request->input('pin'),
                idempotencyKey: $request->input('idempotency_key')
            );

            return redirect()->route('finance.loans.show', $loan)
                ->with('success', 'Pembiayaan disetujui! Kolateral terkunci, unit mobil sudah masuk My Garage.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function payInstallment(Request $request, Loan $loan): RedirectResponse
    {
        $user = $request->user();
        if ((int) $loan->user_id !== (int) $user->id && ! $user->isAdmin()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $installment = $loan->nextUnpaidInstallment();
        if ($installment === null) {
            return redirect()->back()->with('error', 'Tidak ada cicilan yang menunggu pembayaran.');
        }

        try {
            $this->payInstallment->execute($installment);

            return redirect()->back()->with('success', "Cicilan ke-{$installment->sequence} berhasil dibayar.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function payOff(Request $request, Loan $loan): RedirectResponse
    {
        $user = $request->user();
        if ((int) $loan->user_id !== (int) $user->id && ! $user->isAdmin()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        try {
            $this->payInstallment->payOff($loan);

            return redirect()->back()->with('success', 'Pembiayaan lunas! Kolateral kripto sudah dikembalikan ke dompetmu.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function topUpCollateral(Request $request, Loan $loan): RedirectResponse
    {
        $request->validate([
            'qty' => 'required|numeric|gt:0',
        ]);

        try {
            $this->addCollateral->execute($loan, $request->user(), (string) BigDecimal::of((string) $request->input('qty')));

            return redirect()->back()->with('success', 'Kolateral berhasil ditambahkan dan LTV diperbarui.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
