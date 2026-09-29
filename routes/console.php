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
