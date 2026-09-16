<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Filament\Pages\Billing\BulkGenerateMonthlyRent;
use App\Filament\Resources\Billing\InvoicesResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Tek2991\Accounting\Models\Invoice;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoicesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkGenerateRent')
                ->label('Bulk Generate Rent')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('billing.rent.generate') || auth()->user()?->hasAnyRole(['Business Owner', 'Accountant']) || (bool) auth()->user()?->roles->isEmpty())
                ->url(BulkGenerateMonthlyRent::getUrl()),
        ];
    }

    public function getTabs(): array
    {
        $branchScopedQuery = function () {
            $query = Invoice::query();
            app(\Tek2991\Accounting\Services\BranchContext::class)->applyQueryScope($query);
            return $query;
        };

        return [
            'all' => Tab::make('All Invoices')
                ->badge(fn () => $branchScopedQuery()->count())
                ->badgeColor('primary'),

            'rent' => Tab::make('Rent Demands')
                ->icon('heroicon-o-banknotes')
                ->badge(fn () => $branchScopedQuery()->where(function ($q) {
                    $q->where('reference_type', TenancyAgreement::class)
                      ->orWhere('notes', 'like', '%Rent%');
                })->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($q) {
                    $q->where('reference_type', TenancyAgreement::class)
                      ->orWhere('notes', 'like', '%Rent%');
                })),

            'maintenance' => Tab::make('Maintenance')
                ->icon('heroicon-o-wrench-screwdriver')
                ->badge(fn () => $branchScopedQuery()->where(function ($q) {
                    $q->where('reference_type', MaintenanceRequest::class)
                      ->orWhere('notes', 'like', '%Maintenance%');
                })->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($q) {
                    $q->where('reference_type', MaintenanceRequest::class)
                      ->orWhere('notes', 'like', '%Maintenance%');
                })),

            'fees' => Tab::make('Fees & Documentation')
                ->icon('heroicon-o-document-currency-rupee')
                ->badge(fn () => $branchScopedQuery()->where(function ($q) {
                    $q->where('document_snapshot->invoice_category', 'documentation_charge')
                      ->orWhere('notes', 'like', '%documentation%');
                })->count())
                ->badgeColor('purple')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($q) {
                    $q->where('document_snapshot->invoice_category', 'documentation_charge')
                      ->orWhere('notes', 'like', '%documentation%');
                })),

            'unpaid' => Tab::make('Unpaid / Due')
                ->icon('heroicon-o-exclamation-circle')
                ->badge(fn () => $branchScopedQuery()->where('balance_due', '>', 0)->where('status', '!=', 'draft')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('balance_due', '>', 0)->where('status', '!=', 'draft')),
        ];
    }
}
