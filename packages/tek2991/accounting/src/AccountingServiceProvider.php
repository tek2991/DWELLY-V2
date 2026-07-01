<?php

namespace Tek2991\Accounting;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tek2991\Accounting\Services\BranchContext;
use Tek2991\Accounting\Services\AccountService;
use Tek2991\Accounting\Services\TransactionService;
use Tek2991\Accounting\Services\TaxService;
use Tek2991\Accounting\Services\InvoiceService;
use Tek2991\Accounting\Services\BillService;
use Tek2991\Accounting\Services\TaxRegimeResolver;
use Tek2991\Accounting\Services\PeriodLockService;
use Tek2991\Accounting\Services\CreditNoteService;
use Tek2991\Accounting\Services\DebitNoteService;
use Tek2991\Accounting\Services\DocumentNumberService;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

class AccountingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('accounting')
            ->hasConfigFile()
            ->hasMigrations([
                'create_states_table',
                'create_organizations_table',
                'create_gst_registrations_table',
                'create_document_sequences_table',
                'create_contacts_table',
                'create_accounting_tables',
                'create_bank_accounts_table',
                'create_items_table',
                'create_taxes_table',
                'create_invoices_table',
                'create_invoice_items_table',
                'create_bills_table',
                'create_bill_items_table',
                'create_payments_table',
                'create_fiscal_periods_table',
                'create_fiscal_period_events_table',
                'create_credit_notes_table',
                'create_credit_note_items_table',
                'create_debit_notes_table',
                'create_debit_note_items_table',
            ])
            ->hasCommands([
                \Tek2991\Accounting\Commands\ImportOpeningBalancesCommand::class,
                \Tek2991\Accounting\Commands\GenerateFiscalPeriodsCommand::class,
            ])
            ->hasViews('accounting')
            ->hasRoute('web')
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        FilamentAsset::register([
            Css::make('accounting-styles', __DIR__ . '/../resources/dist/accounting.css'),
        ], 'tek2991/accounting');

        $this->app->singleton('accounting', function ($app) {
            return new \Tek2991\Accounting\AccountingManager();
        });

        $this->app->singleton(AccountService::class);
        $this->app->singleton(TransactionService::class);
        $this->app->singleton(TaxRegimeResolver::class);
        $this->app->singleton(BranchContext::class);
        $this->app->singleton(TaxService::class);
        $this->app->singleton(DocumentNumberService::class);
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(BillService::class);
        $this->app->singleton(PeriodLockService::class);
        $this->app->singleton(CreditNoteService::class);
        $this->app->singleton(DebitNoteService::class);
    }

    public function packageBooted(): void
    {
        \Tek2991\Accounting\Models\Contact::observe(\Tek2991\Accounting\Observers\ContactObserver::class);
        \Livewire\Livewire::component('branch-selector', \Tek2991\Accounting\Livewire\BranchSelector::class);
    }
}
