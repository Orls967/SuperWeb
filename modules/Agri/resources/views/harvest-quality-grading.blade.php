@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Harvest Quality & Grading Station</h1>
            <p class="text-gray-600 dark:text-gray-400">Moisture, foreign matter, and physical defect grading with tiered contract pricing tiers.</p>
        </div>
        <a href="{{ route('agri.dashboard') }}" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg">Back to Dashboard</a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-800 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Grade Incoming Harvest</h2>
            <form action="{{ route('agri.grading.evaluate') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contract ID / Number</label>
                    <input type="text" name="contract_id" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" placeholder="AGR-CTR-8812">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gross Weight (Kg)</label>
                    <input type="number" step="0.1" name="gross_weight_kg" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="1200">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Moisture Percentage (%)</label>
                    <input type="number" step="0.1" name="moisture_percentage" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="13.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Defect Percentage (%)</label>
                    <input type="number" step="0.1" name="defect_percentage" required class="w-full mt-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm" value="2.0">
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">Evaluate Harvest Grade</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Grading Standards Matrix</h2>
            <div class="space-y-4">
                <div class="p-4 rounded-lg border border-green-200 bg-green-50 dark:bg-gray-700 dark:border-green-800">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-green-900 dark:text-green-300">GRADE A (Premium Quality)</span>
                        <span class="px-2 py-1 text-xs font-semibold bg-green-200 text-green-800 rounded">100% Floor Price</span>
                    </div>
                    <p class="text-sm text-green-700 dark:text-green-400 mt-1">Moisture &le; 14.0%, Visual Defect &lt; 3.0%, Foreign Matter &lt; 1.0%. Ideal for central kitchen & direct consumer retail.</p>
                </div>
                <div class="p-4 rounded-lg border border-yellow-200 bg-yellow-50 dark:bg-gray-700 dark:border-yellow-800">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-yellow-900 dark:text-yellow-300">GRADE B (Standard Processing)</span>
                        <span class="px-2 py-1 text-xs font-semibold bg-yellow-200 text-yellow-800 rounded">90% Floor Price</span>
                    </div>
                    <p class="text-sm text-yellow-700 dark:text-yellow-400 mt-1">Moisture &le; 16.0%, Visual Defect &lt; 7.0%, Foreign Matter &lt; 2.0%. Routed to food manufacturing paste & canning line.</p>
                </div>
                <div class="p-4 rounded-lg border border-red-200 bg-red-50 dark:bg-gray-700 dark:border-red-800">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-red-900 dark:text-red-300">GRADE C (Secondary Utility)</span>
                        <span class="px-2 py-1 text-xs font-semibold bg-red-200 text-red-800 rounded">80% Floor Price</span>
                    </div>
                    <p class="text-sm text-red-700 dark:text-red-400 mt-1">Moisture &gt; 16.0% or Defects &ge; 7.0%. Sorted for animal feed or industrial extraction.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
