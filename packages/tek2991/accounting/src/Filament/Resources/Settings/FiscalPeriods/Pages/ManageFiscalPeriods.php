<?php

namespace Tek2991\Accounting\Filament\Resources\Settings\FiscalPeriods\Pages;

use Tek2991\Accounting\Filament\Resources\Settings\FiscalPeriods\FiscalPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageFiscalPeriods extends ManageRecords
{
    protected static string $resource = FiscalPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
