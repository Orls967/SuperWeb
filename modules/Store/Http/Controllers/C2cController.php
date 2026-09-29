<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Store\Application\Actions\CancelC2cOrderAction;
use Modules\Store\Application\Actions\ConfirmC2cReceiptAction;
use Modules\Store\Application\Actions\DisputeC2cOrderAction;
use Modules\Store\Application\Actions\ListVehicleForSaleAction;
use Modules\Store\Application\Actions\MarkC2cHandoverAction;
use Modules\Store\Application\Actions\PurchaseC2cVehicleAction;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\Product;
use Modules\Store\Http\Requests\PurchaseC2cRequest;
use Modules\Store\Http\Requests\StoreC2cListingRequest;

class C2cController extends Controller
{
    public function __construct(
        private readonly ListVehicleForSaleAction $listVehicleForSale,
        private readonly PurchaseC2cVehicleAction $purchaseVehicle,
        private readonly MarkC2cHandoverAction $markHandover,
        private readonly ConfirmC2cReceiptAction $confirmReceipt,
        private readonly DisputeC2cOrderAction $disputeOrder,
        private readonly CancelC2cOrderAction $cancelOrder,
    ) {}

    /**
     * Marketplace mobil bekas antar pengguna.
     */
    public function index(Request $request): View
    {
        $listings = Product::c2c()
            ->listed()
            ->where('cached_stock', '>', 0)
            ->with(['seller', 'productable.car.brand'])
            ->latest()
            ->paginate(12);

        return view('store::c2c.index', [
            'listings' => $listings,
        ]);
    }

    /**
     * Daftar listing dan transaksi C2C milik pengguna (sebagai penjual).
     */
    public function mySales(Request $request): View
    {
        $user = $request->user();

        $listings = Product::c2c()
            ->where('seller_id', $user->id)
            ->with('productable.car.brand')
            ->latest()
            ->get();

        $sales = Order::where('seller_id', $user->id)
            ->with(['items.product', 'user'])
            ->latest()
            ->get();

        $sellableVehicles = Vehicle::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('car.brand')
            ->get()
            ->reject(fn (Vehicle $vehicle) => $listings->contains(
                fn (Product $p) => (int) $p->productable_id === (int) $vehicle->id && $p->is_listed
            ))
            ->values();

        return view('store::c2c.my-sales', [
            'listings' => $listings,
            'sales' => $sales,
            'sellableVehicles' => $sellableVehicles,
        ]);
    }

    public function store(StoreC2cListingRequest $request): RedirectResponse
    {
        $user = $request->user();
        $vehicle = Vehicle::findOrFail($request->integer('vehicle_id'));

        try {
            $product = $this->listVehicleForSale->execute(
                seller: $user,
                vehicle: $vehicle,
                price: $request->integer('price'),
                description: $request->input('description')
            );

            return redirect()->route('store.c2c.mySales')
                ->with('success', "Mobil {$product->name} berhasil dipasang di Store seharga {$product->formatted_price}.");
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function unlist(Request $request, Product $product): RedirectResponse
    {
        try {
            $this->listVehicleForSale->unlist($request->user(), $product);

            return redirect()->route('store.c2c.mySales')->with('success', 'Listing berhasil ditarik dari Store.');
        } catch (Exception $e) {
            return redirect()->route('store.c2c.mySales')->with('error', $e->getMessage());
        }
    }

    /**
     * Halaman pembelian dengan penjelasan escrow dan input PIN.
     */
    public function buyForm(Request $request, Product $product): View|RedirectResponse
    {
        if (! $product->isC2c() || ! $product->is_listed) {
            return redirect()->route('store.c2c.index')->with('error', 'Listing ini sudah tidak tersedia.');
        }

        $user = $request->user();
        $vehicle = Vehicle::with('car.brand')->find($product->productable_id);

        return view('store::c2c.buy', [
            'product' => $product->load('seller'),
            'vehicle' => $vehicle,
            'walletBalance' => (int) $user->walletAccount('IDR')->cached_balance,
            'platformFeePercent' => Order::C2C_PLATFORM_FEE_PERCENT,
            'autoCaptureDays' => Order::C2C_AUTO_CAPTURE_DAYS,
        ]);
    }

    public function buy(PurchaseC2cRequest $request, Product $product): RedirectResponse
    {
        try {
            $order = $this->purchaseVehicle->execute(
                buyer: $request->user(),
                product: $product,
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

            return redirect()->route('store.orders.show', $order)
                ->with('success', "Dana sebesar {$order->formatted_grand_total} ditahan di escrow. Tunggu penjual menyerahkan kendaraan.");
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function handover(Request $request, Order $order): RedirectResponse
    {
        try {
            $this->markHandover->execute($order, $request->user());

            return redirect()->route('store.orders.show', $order)
                ->with('success', 'Serah terima tercatat. Dana akan cair setelah pembeli konfirmasi atau otomatis dalam 3 hari.');
        } catch (Exception $e) {
            return redirect()->route('store.orders.show', $order)->with('error', $e->getMessage());
        }
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        try {
            $this->confirmReceipt->execute($order, $request->user());

            return redirect()->route('store.orders.show', $order)
                ->with('success', 'Kendaraan resmi menjadi milikmu dan sudah masuk ke My Garage beserta paspor digitalnya.');
        } catch (Exception $e) {
            return redirect()->route('store.orders.show', $order)->with('error', $e->getMessage());
        }
    }

    public function dispute(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:1000',
        ]);

        try {
            $this->disputeOrder->execute($order, $request->user(), (string) $request->input('reason'));

            return redirect()->route('store.orders.show', $order)
                ->with('success', 'Sengketa diajukan. Dana tetap aman di escrow sampai admin memutuskan.');
        } catch (Exception $e) {
            return redirect()->route('store.orders.show', $order)->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();

        if ((int) $order->user_id !== (int) $user->id
            && (int) $order->seller_id !== (int) $user->id
            && ! $user->isAdmin()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        try {
            $this->cancelOrder->execute($order, (string) $request->input('reason', 'Dibatalkan oleh pengguna'));

            return redirect()->route('store.orders.show', $order)
                ->with('success', 'Transaksi dibatalkan dan dana escrow dikembalikan penuh ke pembeli.');
        } catch (Exception $e) {
            return redirect()->route('store.orders.show', $order)->with('error', $e->getMessage());
        }
    }
}
