<?php

namespace Tek2991\Accounting;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tek2991\Accounting\Filament\Pages\AccountChart;
use Tek2991\Accounting\Filament\Pages\AccountingSettings;
use Tek2991\Accounting\Filament\Resources\Accounts\AccountResource;
use Tek2991\Accounting\Filament\Resources\Banking\BankAccountResource;
use Tek2991\Accounting\Filament\Resources\Transactions\TransactionResource;
use Tek2991\Accounting\Filament\Resources\Items\ItemResource;
use Tek2991\Accounting\Filament\Resources\Contacts\ContactResource;
use Tek2991\Accounting\Filament\Resources\Taxes\TaxResource;
use Tek2991\Accounting\Filament\Resources\Sales\Invoices\InvoiceResource;
use Tek2991\Accounting\Filament\Resources\Purchases\Bills\BillResource;
use Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\CreditNoteResource;
use Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\DebitNoteResource;
use Tek2991\Accounting\Filament\Resources\Settings\FiscalPeriods\FiscalPeriodResource;
use Tek2991\Accounting\Filament\Widgets\AccountBalanceOverview;
use Tek2991\Accounting\Filament\Pages\Reports\BalanceSheet;
use Tek2991\Accounting\Filament\Pages\Reports\ProfitAndLoss;
use Tek2991\Accounting\Filament\Pages\Reports\TrialBalance;
use Tek2991\Accounting\Filament\Pages\Reports\TaxSummary;
use Tek2991\Accounting\Filament\Pages\Reports\AccountLedger;
use Tek2991\Accounting\Filament\Pages\Reports\VendorLedger;
use Tek2991\Accounting\Filament\Pages\Reports\CustomerLedger;

class AccountingPlugin implements Plugin
{
    protected bool $hasAccountChart = true;

    protected bool $hasWidgets = true;

    protected bool $hasReports = true;

    protected bool $hasBanking = true;

    protected bool $hasCatalog = true;

    public function getId(): string
    {
        return 'tek-accounting';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    // ──────────────────────────────────────────────────────────────
    // Fluent configuration
    // ──────────────────────────────────────────────────────────────

    public function accountChart(bool $condition = true): static
    {
        $this->hasAccountChart = $condition;

        return $this;
    }

    public function widgets(bool $condition = true): static
    {
        $this->hasWidgets = $condition;

        return $this;
    }

    public function banking(bool $condition = true): static
    {
        $this->hasBanking = $condition;

        return $this;
    }

    public function catalog(bool $condition = true): static
    {
        $this->hasCatalog = $condition;

        return $this;
    }

    public function reports(bool $condition = true): static
    {
        $this->hasReports = $condition;

        return $this;
    }

    // ──────────────────────────────────────────────────────────────
    // Registration
    // ──────────────────────────────────────────────────────────────

    public function register(Panel $panel): void
    {
        // ── Resources ─────────────────────────────────────────────
        $resources = [
            AccountResource::class,
            TransactionResource::class,
            InvoiceResource::class,
            CreditNoteResource::class,
            BillResource::class,
            DebitNoteResource::class,
            FiscalPeriodResource::class,
        ];

        if ($this->hasBanking) {
            $resources[] = BankAccountResource::class;
        }

        if ($this->hasCatalog) {
            $resources[] = ItemResource::class;
            $resources[] = ContactResource::class;
            $resources[] = TaxResource::class;
        }

        $panel->resources($resources);

        // ── Pages ─────────────────────────────────────────────────
        $pages = [AccountingSettings::class];

        if ($this->hasAccountChart) {
            $pages[] = AccountChart::class;
        }

        if ($this->hasReports) {
            $pages = array_merge($pages, [
                BalanceSheet::class,
                ProfitAndLoss::class,
                TrialBalance::class,
                TaxSummary::class,
                AccountLedger::class,
                VendorLedger::class,
                CustomerLedger::class,
            ]);
        }

        $panel->pages($pages);

        // ── Widgets ───────────────────────────────────────────────
        if ($this->hasWidgets) {
            $panel->widgets([
                \Tek2991\Accounting\Filament\Widgets\FinancialSnapshotWidget::class,
                \Tek2991\Accounting\Filament\Widgets\QuickActionsWidget::class,
                \Tek2991\Accounting\Filament\Widgets\CashPositionWidget::class,
                \Tek2991\Accounting\Filament\Widgets\OutstandingReceivablesWidget::class,
                \Tek2991\Accounting\Filament\Widgets\OutstandingPayablesWidget::class,
                \Tek2991\Accounting\Filament\Widgets\RevenueExpenseChartWidget::class,
                \Tek2991\Accounting\Filament\Widgets\GstOverviewWidget::class,
                \Tek2991\Accounting\Filament\Widgets\RecentActivityWidget::class,
            ]);
        }

        $panel->navigationGroups([
            'Sales',
            'Purchases',
            'Banking',
            'Accounting',
            'Reports',
            'Catalog',
            'Settings',
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
