<?php

namespace Tek2991\Accounting\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Tek2991\Accounting\Contracts\CompanyAccessor;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Models\Account;
use Illuminate\Support\Collection;
use Tek2991\Accounting\Filament\Resources\Accounts\Schemas\AccountForm;

class AccountChart extends Page
{
    protected string $view = 'accounting::filament.pages.account-chart';

    protected static \UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static \BackedEnum|string|null $navigationIcon = null;

    protected static ?string $navigationLabel = 'Chart of Accounts';

    protected static ?int $navigationSort = 2;

    /**
     * The currently active category tab (URL-persisted).
     */
    #[Url]
    public string $activeTab = 'asset';

    // ──────────────────────────────────────────────────────────────
    // Computed data
    // ──────────────────────────────────────────────────────────────

    /**
     * Accounts grouped by ReportingClass for the active tab.
     * Returns Collection<string, Account[]>
     */
    #[Computed]
    public function accountsByReportingClass(): Collection
    {
        $type = AccountType::from($this->activeTab);

        $accounts = Account::where('type', $type)
            ->withCount('children')
            ->orderBy('code')
            ->get();

        return $accounts->groupBy(function (Account $account) {
            return $account->reporting_class->getLabel();
        });
    }

    /**
     * All AccountType cases for rendering the tab bar.
     */
    public function getCategories(): array
    {
        return AccountType::cases();
    }

    // ──────────────────────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────────────────────

    protected function configureAction(Action $action): void
    {
        $action
            ->slideOver()
            ->modalWidth('2xl');
    }

    /**
     * Create a new account inline (pre-filled with the active category defaults).
     */
    public function createAccountAction(): Action
    {
        return CreateAction::make('createAccount')
            ->label('New Account')
            ->icon('heroicon-o-plus')
            ->model(Account::class)
            ->form(fn (Schema $schema) => AccountForm::configure($schema))
            ->fillForm(fn (array $arguments) => $this->getAccountFormDefaults($arguments))
            ->mutateDataUsing(function (array $data) {
                return $data;
            })
            ->successNotificationTitle('Account created successfully');
    }

    /**
     * Edit an existing account (keyed by account ID passed as argument).
     */
    public function editAccountAction(): Action
    {
        return EditAction::make('editAccount')
            ->label('Edit')
            ->icon('heroicon-o-pencil-square')
            ->iconButton()
            ->record(fn (array $arguments) => Account::find($arguments['account'] ?? null))
            ->form(fn (Schema $schema) => AccountForm::configure($schema))
            ->successNotificationTitle('Account updated successfully');
    }

    /**
     * Quick-toggle archive status for an account.
     */
    public function toggleArchiveAction(): Action
    {
        return Action::make('toggleArchive')
            ->label(fn (array $arguments) => Account::find($arguments['account'] ?? null)?->archived ? 'Restore' : 'Archive')
            ->icon(fn (array $arguments) => Account::find($arguments['account'] ?? null)?->archived ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-archive-box')
            ->color(fn (array $arguments) => Account::find($arguments['account'] ?? null)?->archived ? 'success' : 'warning')
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                $account = Account::find($arguments['account'] ?? null);
                if ($account) {
                    $account->update(['archived' => ! $account->archived]);
                    Notification::make()
                        ->success()
                        ->title($account->archived ? 'Account archived' : 'Account restored')
                        ->send();
                }
            });
    }

    /**
     * Add a new account scoped to a specific ReportingClass.
     */
    public function createAccountForClassAction(): Action
    {
        return CreateAction::make('createAccountForClass')
            ->label('Add account')
            ->link()
            ->icon('heroicon-o-plus-circle')
            ->model(Account::class)
            ->form(fn (Schema $schema) => AccountForm::configure($schema))
            ->fillForm(fn (array $arguments) => $this->getAccountFormDefaultsForClass($arguments['className'] ?? null))
            ->mutateDataUsing(function (array $data) {
                return $data;
            })
            ->successNotificationTitle('Account created successfully');
    }

    private function getAccountFormDefaults(array $arguments): array
    {
        $type = AccountType::tryFrom($this->activeTab);
        $defaults = [
            'type'      => $this->activeTab,
            'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
        ];

        return $defaults;
    }

    private function getAccountFormDefaultsForClass(?string $className): array
    {
        $defaults = [
            'type'      => $this->activeTab,
            'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
        ];

        if ($className) {
            $class = collect(ReportingClass::cases())->first(fn($c) => $c->getLabel() === $className);
            if ($class) {
                $defaults['reporting_class'] = $class->value;
            }
        }

        return $defaults;
    }

    // ──────────────────────────────────────────────────────────────
    // Header actions
    // ──────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            $this->createAccountAction(),
        ];
    }
}
