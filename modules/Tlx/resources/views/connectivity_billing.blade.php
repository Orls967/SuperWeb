<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IoT Connectivity Invoices</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <h1 class="text-xl font-bold mb-6">IoT Group Connectivity Invoices</h1>
        <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
            <div class="overflow-x-auto max-w-full">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-700 text-gray-300">
                        <tr>
                            <th class="p-2">Invoice No</th>
                            <th class="p-2">Owner Entity</th>
                            <th class="p-2">Period</th>
                            <th class="p-2">Devices</th>
                            <th class="p-2">Total Charge</th>
                            <th class="p-2">Status</th>
                            <th class="p-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($invoices as $inv)
                            <tr>
                                <td class="p-2 font-mono">{{ $inv->invoice_number }}</td>
                                <td class="p-2">{{ $inv->owner_entity_id }}</td>
                                <td class="p-2">{{ $inv->billing_period }}</td>
                                <td class="p-2">{{ $inv->active_device_count }}</td>
                                <td class="p-2">Rp {{ number_format($inv->total_charge_minor) }}</td>
                                <td class="p-2">
                                    <span class="px-2 py-0.5 rounded text-xs {{ $inv->status === 'SETTLED' ? 'bg-green-900 text-green-300' : 'bg-blue-900 text-blue-300' }}">
                                        {{ $inv->status }}
                                    </span>
                                </td>
                                <td class="p-2">
                                    <a href="/telecom/billing/connectivity/{{ $inv->id }}" class="text-blue-400 hover:underline">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-3 text-center text-gray-400">Belum ada tagihan konektivitas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

