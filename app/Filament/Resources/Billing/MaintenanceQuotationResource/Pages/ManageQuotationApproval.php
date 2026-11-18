<?php

namespace App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages;

use App\Filament\Resources\Billing\MaintenanceQuotationResource;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\Concerns\HasMaintenanceQuotationWorkflowHeader;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Schemas\MaintenanceQuotationForm;
use BackedEnum;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageQuotationApproval extends EditRecord
{
    use HasMaintenanceQuotationWorkflowHeader;

    protected static string $resource = MaintenanceQuotationResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?string $navigationLabel = '3. Client Approval';

    protected static ?string $title = 'Maintenance Quotation – Client Decision & Approval Proof';

    public function form(Schema $schema): Schema
    {
        return MaintenanceQuotationForm::configureApprovalForm($schema);
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()?->maintenanceRequest?->payer_type?->isDwellyAbsorbed()) {
            redirect(ManageQuotationWorkOrders::getUrl(['record' => $this->getRecord()]));
        }
    }

    public function approveQuoteInTabAction(): \Filament\Actions\Action
    {
        return MaintenanceQuotationForm::getApproveQuotationAction('approveQuoteInTab');
    }

    public function approveQuoteInTabBottomAction(): \Filament\Actions\Action
    {
        return MaintenanceQuotationForm::getApproveQuotationAction('approveQuoteInTabBottom');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getSavedNotification(): ?\Filament\Notifications\Notification
    {
        return null;
    }
}
