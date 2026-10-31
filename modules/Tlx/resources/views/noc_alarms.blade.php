<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NOC Alarm & Incident Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col gap-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h1 class="text-xl font-bold">NOC Real-Time Alarm Monitor</h1>
                <span class="px-3 py-1 bg-blue-900 text-blue-300 rounded text-xs">On-Call Operator 375px Ready</span>
            </div>

            <!-- Open Incidents -->
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <h2 class="text-lg font-semibold mb-3">Active Incident Tickets</h2>
                <div class="grid grid-cols-1 gap-4">
                    @forelse($tickets as $t)
                        <div class="bg-gray-900 p-3 rounded border border-gray-700 flex flex-col sm:flex-row justify-between gap-2">
                            <div>
                                <span class="font-mono text-sm font-bold text-red-400">{{ $t->ticket_number }}</span>
                                <span class="ml-2 px-2 py-0.5 text-xs bg-red-900 text-red-300 rounded">{{ $t->severity }}</span>
                                <p class="text-xs text-gray-400 mt-1">SLA Target: {{ $t->sla_target_minutes }} mins</p>
                            </div>
                            <span class="text-xs text-yellow-400 font-semibold">{{ $t->status }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">Tidak ada tiket insiden aktif.</p>
                    @endforelse
                </div>
            </div>

            <!-- Ingested Alarms Table -->
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <h2 class="text-lg font-semibold mb-3">Live Network Alarms (Append-Only)</h2>
                <div class="overflow-x-auto max-w-full">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-700 text-gray-300">
                            <tr>
                                <th class="p-2">Alarm Code</th>
                                <th class="p-2">Type</th>
                                <th class="p-2">Severity</th>
                                <th class="p-2">Suppressed?</th>
                                <th class="p-2">Raised At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($alarms as $a)
                                <tr class="hover:bg-gray-750">
                                    <td class="p-2 font-mono">{{ $a->alarm_code }}</td>
                                    <td class="p-2">{{ $a->alarm_type }}</td>
                                    <td class="p-2">
                                        <span class="px-2 py-0.5 rounded text-xs {{ $a->severity === 'CRITICAL' ? 'bg-red-900 text-red-300' : 'bg-yellow-900 text-yellow-300' }}">
                                            {{ $a->severity }}
                                        </span>
                                    </td>
                                    <td class="p-2">
                                        @if($a->is_suppressed)
                                            <span class="text-gray-400 italic">Suppressed (Downstream)</span>
                                        @else
                                            <span class="text-red-400 font-bold">ROOT CAUSE</span>
                                        @endif
                                    </td>
                                    <td class="p-2 text-gray-400">{{ $a->raised_at }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-3 text-center text-gray-400">Belum ada alarm tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

