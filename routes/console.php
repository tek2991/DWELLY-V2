<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\GenerateMonthlyRentInvoices;

Schedule::command(GenerateMonthlyRentInvoices::class)->monthlyOn(1, '00:00');
