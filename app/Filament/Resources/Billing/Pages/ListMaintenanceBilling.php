<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Filament\Resources\Billing\MaintenanceBillingResource;
use App\Filament\Resources\Billing\Widgets\MaintenanceBillsTableWidget;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Invoice;

class ListMaintenanceBilling extends ListRecords
{
    protected static string $resource = MaintenanceBillingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('MaintenanceBillingTabs')
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('invoices')
                            ->label('Client Invoices (Receivable)')
                            ->icon('heroicon-o-document-text')
                            ->badge(function () {
                                $query = Invoice::query()
                                    ->where(function ($q) {
                                        $q->where('reference_type', MaintenanceRequest::class)
                                            ->orWhere('notes', 'like', '%Maintenance%');
                                    });
                                app(\Tek2991\Accounting\Services\BranchContext::class)->applyQueryScope($query);

                                return $query->count();
                            })
                            ->badgeColor('primary')
                            ->schema([
                                EmbeddedTable::make(),
                            ]),

                        Tab::make('bills')
                            ->label('Contractor Bills (Payable)')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->badge(function () {
                                $query = Bill::query()
                                    ->where(function ($q) {
                                        $q->where('reference_type', MaintenanceRequest::class)
                                            ->orWhere('notes', 'like', '%Maintenance%')
                                            ->orWhere('notes', 'like', '%Ticket%')
                                            ->orWhere('notes', 'like', '%Work Order%');
                                    });
                                app(\Tek2991\Accounting\Services\BranchContext::class)->applyQueryScope($query);

                                return $query->count();
                            })
                            ->badgeColor('warning')
                            ->schema([
                                Livewire::make(MaintenanceBillsTableWidget::class),
                            ]),
                    ]),
            ]);
    }
}
