<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bank:reconcile')->daily();
Schedule::command('payment:release-expired-holds')->daily();
Schedule::command('store:cancel-stale-orders')->everyTenMinutes();
Schedule::command('store:auto-capture-c2c')->hourly();
Schedule::command('crypto:tick')->everyMinute();
Schedule::command('finance:charge-installments')->dailyAt('01:00');
// --- Kuliner (RM Sari Ranah) ---
Schedule::command('resto:expire-display')->everyFifteenMinutes();
Schedule::command('resto:close-day')->dailyAt('23:59');
Schedule::command('resto:post-royalty')->dailyAt('02:00');
Schedule::command('resto:check-stock')->dailyAt('07:30');

// --- Properti (Duta Mall) ---
// Tagihan bulanan diterbitkan setiap tanggal 1; command-nya idempoten per lease+periode
Schedule::command('mall:generate-invoices')->monthlyOn(1, '03:00');
Schedule::command('mall:auto-debit')->dailyAt('04:00');
Schedule::command('mall:apply-penalties')->dailyAt('05:00');
Schedule::command('mall:renew-parking-members')->dailyAt('06:00');
Schedule::command('mall:audit-billing')->dailyAt('07:00');
