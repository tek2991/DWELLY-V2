<?php

namespace App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages;

use App\Filament\Resources\Billing\MaintenanceQuotationResource;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\Concerns\HasMaintenanceQuotationWorkflowHeader;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Schemas\MaintenanceQuotationForm;
use BackedEnum;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageQuotationWorkOrders extends EditRecord
{
    use HasMaintenanceQuotationWorkflowHeader;

    protected static string $resource = MaintenanceQuotationResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = '4. Vendor Work Orders';

    protected static ?string $title = 'Maintenance Quotation – Contractor Awarding & Work Orders';

    public function form(Schema $schema): Schema
    {
        return MaintenanceQuotationForm::configureWorkOrdersForm($schema);
    }
}
