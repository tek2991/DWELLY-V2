<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Filament\Resources\Billing\MaintenanceBillingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceBilling extends CreateRecord
{
    protected static string $resource = MaintenanceBillingResource::class;
}
