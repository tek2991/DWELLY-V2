<?php

namespace Tek2991\Accounting\Filament\Resources\Taxes\Pages;

use Tek2991\Accounting\Filament\Resources\Taxes\TaxResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTax extends EditRecord
{
    protected static string $resource = TaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
