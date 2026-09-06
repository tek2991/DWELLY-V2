<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\GenerateMonthlyRent;
use App\Console\Commands\CheckAgreementRenewals;

Schedule::command(GenerateMonthlyRent::class)->monthlyOn(1, '00:00');
Schedule::command(CheckAgreementRenewals::class)->dailyAt('08:00');
