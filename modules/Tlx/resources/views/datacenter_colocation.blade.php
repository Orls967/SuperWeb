<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DC Colocation & Contracts</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <h1 class="text-xl font-bold mb-6">Data Center Colocation Contracts</h1>
        <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
            <div class="overflow-x-auto max-w-full">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-700 text-gray-300">
                        <tr>
                            <th class="p-2">Contract No</th>
                            <th class="p-2">Tenant</th>
                            <th class="p-2">Racks</th>
                            <th class="p-2">Commit Power</th>
                            <th class="p-2">Port Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($contracts as $c)
                            <tr>
                                <td class="p-2 font-mono">{{ $c->contract_number }}</td>
                                <td class="p-2">{{ $c->tenant_party_id }}</td>
                                <td class="p-2">{{ $c->rack_units_allocated }}</td>
                                <td class="p-2">{{ $c->power_commit_kw }} kW</td>
                                <td class="p-2">
                                    <span class="px-2 py-0.5 rounded text-xs {{ $c->port_status === 'UP' ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300' }}">
                                        {{ $c->port_status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-3 text-center text-gray-400">Belum ada kontrak colocation.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

