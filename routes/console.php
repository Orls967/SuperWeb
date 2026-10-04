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
Schedule::command('mall:expire-points')->dailyAt('00:30');
Schedule::command('mall:expire-vouchers')->dailyAt('00:45');
Schedule::command('mall:settle-vouchers')->weeklyOn(1, '08:00');
Schedule::command('mall:generate-pm-orders')->dailyAt('06:30');

// --- Logistik (Sari Ranah Express) ---
Schedule::command('lgx:invoice-shippers')->monthlyOn(1, '02:00');
Schedule::command('lgx:detect-late')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('lgx:retry-webhooks')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('lgx:capacity-check')->hourly();
Schedule::command('lgx:settle-cod')->dailyAt('08:30');
Schedule::command('lgx:pay-carriers')->weeklyOn(1, '09:00');
Schedule::command('lgx:accrue-dd')->dailyAt('00:10');
Schedule::command('lgx:audit-billing')->dailyAt('07:15');
Schedule::command('lgx:verify-custody')->dailyAt('02:30');

// --- Party Master & Compliance ---
Schedule::command('party:remind-expiring-docs')->dailyAt('08:00');
