<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <h1 class="text-xl font-bold mb-4 font-mono">{{ $invoice->invoice_number }}</h1>
            <div class="grid grid-cols-2 gap-4 text-sm mb-6">
                <div>
                    <span class="text-gray-400">Owner Entity:</span>
                    <p class="font-semibold">{{ $invoice->owner_entity_id }}</p>
                </div>
                <div>
                    <span class="text-gray-400">Billing Period:</span>
                    <p class="font-semibold">{{ $invoice->billing_period }}</p>
                </div>
                <div>
                    <span class="text-gray-400">Active Devices:</span>
                    <p class="font-semibold">{{ $invoice->active_device_count }}</p>
                </div>
                <div>
                    <span class="text-gray-400">Total Charge:</span>
                    <p class="font-semibold text-green-400 font-mono">Rp {{ number_format($invoice->total_charge_minor) }}</p>
                </div>
            </div>
            <a href="/telecom/billing/connectivity" class="text-blue-400 text-sm hover:underline">&larr; Kembali ke Daftar Tagihan</a>
        </div>
    </div>
</body>
</html>

