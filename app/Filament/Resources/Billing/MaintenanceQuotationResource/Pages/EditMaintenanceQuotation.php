<?php

namespace App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages;

use App\Filament\Resources\Billing\MaintenanceQuotationResource;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\Concerns\HasMaintenanceQuotationWorkflowHeader;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Schemas\MaintenanceQuotationForm;
use BackedEnum;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditMaintenanceQuotation extends EditRecord
{
    use HasMaintenanceQuotationWorkflowHeader;

    protected static string $resource = MaintenanceQuotationResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyRupee;

    protected static ?string $navigationLabel = '1. Vendor Quotes';

    protected static ?string $title = 'Maintenance Quotation – Multi-Vendor Trade Estimates';

    public function form(Schema $schema): Schema
    {
        return MaintenanceQuotationForm::configureVendorQuotesForm($schema);
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
