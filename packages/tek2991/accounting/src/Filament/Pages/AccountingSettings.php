<?php

namespace Tek2991\Accounting\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Tek2991\Accounting\Contracts\CompanyAccessor;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Models\Setting;
use Tek2991\Accounting\Enums\TaxRegimeType;
use Tek2991\Accounting\Models\CompanyProfile;
use Tek2991\Accounting\Models\State;

class AccountingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = null;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Accounting Settings';
    
    protected ?string $heading = 'Accounting Settings';

    protected string $view = 'accounting::filament.pages.accounting-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $companyId = app(CompanyAccessor::class)->getCurrentCompanyId();

        $setting = Setting::firstOrCreate(
            ['company_id' => $companyId],
            [
                'default_currency'  => config('accounting.default_currency', 'USD'),
                'company_name'      => null,
                'company_email'     => null,
                'company_address'   => null,
                'company_phone'     => null,
                'company_tax_id'    => null,
                'invoice_prefix'    => 'INV-',
                'bill_prefix'       => 'BILL-',
                'payment_prefix'    => 'PAY-',
                'journal_prefix'    => 'JRNL-',
            ]
        );

        $profile = CompanyProfile::firstOrCreate(
            ['company_id' => $companyId],
            ['tax_regime' => TaxRegimeType::Generic]
        );

        $this->form->fill([
            ...$setting->toArray(),
            'tax_regime' => $profile->tax_regime->value,
            'company_state_id' => $profile->state_id,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Settings')
                    ->description('Manage your accounting preferences.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('default_currency')
                            ->label('Default Currency')
                            ->options($this->getCurrencyOptions())
                            ->searchable()
                            ->required()
                            ->disabled(function () {
                                $companyId = app(\Tek2991\Accounting\Contracts\CompanyAccessor::class)->getCurrentCompanyId();
                                return \Tek2991\Accounting\Models\Transaction::where('company_id', $companyId)->exists();
                            })
                            ->helperText(function () {
                                $companyId = app(\Tek2991\Accounting\Contracts\CompanyAccessor::class)->getCurrentCompanyId();
                                return \Tek2991\Accounting\Models\Transaction::where('company_id', $companyId)->exists() 
                                    ? 'Currency cannot be changed after transactions have been posted to prevent reporting inconsistencies.' 
                                    : 'Base currency for all accounting reports.';
                            }),
                    ]),
                Section::make('Company Profile')
                    ->description('Your company details for invoices and bills.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->label('Company Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('company_email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('company_phone')
                            ->label('Phone Number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('company_tax_id')
                            ->label('Tax ID / GSTIN')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('company_address')
                            ->label('Address')
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Tax Regime')
                    ->description('Configure your regional tax settings.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('tax_regime')
                            ->label('Tax Regime')
                            ->options(TaxRegimeType::class)
                            ->default(TaxRegimeType::Generic->value)
                            ->required()
                            ->live(),
                            
                        Forms\Components\Select::make('company_state_id')
                            ->label('Company State')
                            ->options(fn () => State::pluck('name', 'id'))
                            ->searchable()
                            ->required(fn (Get $get) => $get('tax_regime') === TaxRegimeType::IndiaGst || $get('tax_regime') === TaxRegimeType::IndiaGst->value)
                            ->disabled(fn (Get $get) => !($get('tax_regime') === TaxRegimeType::IndiaGst || $get('tax_regime') === TaxRegimeType::IndiaGst->value))
                            ->dehydrated(),
                    ]),
                    
                Section::make('Document Numbering')
                    ->description('Set prefixes for automatically generated document numbers.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('invoice_prefix')
                            ->label('Invoice Prefix')
                            ->required()
                            ->default('INV-'),
                        Forms\Components\TextInput::make('bill_prefix')
                            ->label('Bill Prefix')
                            ->required()
                            ->default('BILL-'),
                        Forms\Components\TextInput::make('payment_prefix')
                            ->label('Payment Prefix')
                            ->required()
                            ->default('PAY-'),
                        Forms\Components\TextInput::make('journal_prefix')
                            ->label('Journal Entry Prefix')
                            ->required()
                            ->default('JRNL-'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data      = $this->form->getState();
        $companyId = app(CompanyAccessor::class)->getCurrentCompanyId();
        $setting   = Setting::where('company_id', $companyId)->first();

        Setting::updateOrCreate(
            ['company_id' => $companyId],
            [
                'default_currency'  => $data['default_currency'] ?? $setting?->default_currency ?? config('accounting.default_currency', 'USD'),
                'company_name'      => $data['company_name'] ?? null,
                'company_email'     => $data['company_email'] ?? null,
                'company_phone'     => $data['company_phone'] ?? null,
                'company_tax_id'    => $data['company_tax_id'] ?? null,
                'company_address'   => $data['company_address'] ?? null,
                'invoice_prefix'    => $data['invoice_prefix'],
                'bill_prefix'       => $data['bill_prefix'],
                'payment_prefix'    => $data['payment_prefix'],
                'journal_prefix'    => $data['journal_prefix'],
            ]
        );

        CompanyProfile::updateOrCreate(
            ['company_id' => $companyId],
            [
                'tax_regime' => $data['tax_regime'],
                'state_id'   => $data['company_state_id'] ?? null,
            ]
        );

        Notification::make()
            ->success()
            ->title('Settings Saved')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('seedChartOfAccounts')
                ->label('Seed Default Chart of Accounts')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Seed Default Chart of Accounts')
                ->modalDescription('This will create the standard chart of accounts and subtypes for this company. Existing accounts will not be overwritten.')
                ->modalSubmitActionLabel('Seed Accounts')
                ->action(function () {
                    $companyId    = app(CompanyAccessor::class)->getCurrentCompanyId();
                    $currencyCode = $this->form->getState()['default_currency'] ?? config('accounting.default_currency', 'USD');

                    if (! $companyId) {
                        Notification::make()
                            ->danger()
                            ->title('No company context')
                            ->body('Cannot seed accounts without a company context.')
                            ->send();

                        return;
                    }

                    (new DefaultChartOfAccountsSeeder())->run($companyId, $currencyCode);

                    Notification::make()
                        ->success()
                        ->title('Chart of Accounts Seeded')
                        ->body('Default accounts and subtypes have been created.')
                        ->send();
                }),
        ];
    }

    protected function getCurrencyOptions(): array
    {
        try {
            $names      = \Symfony\Component\Intl\Currencies::getNames();
            $formatted  = [];
            foreach ($names as $code => $name) {
                $formatted[$code] = "{$name} ({$code})";
            }

            return $formatted;
        } catch (\Throwable) {
            return [
                'USD' => 'US Dollar (USD)',
                'EUR' => 'Euro (EUR)',
                'GBP' => 'British Pound (GBP)',
                'INR' => 'Indian Rupee (INR)',
                'AUD' => 'Australian Dollar (AUD)',
                'CAD' => 'Canadian Dollar (CAD)',
                'JPY' => 'Japanese Yen (JPY)',
                'CNY' => 'Chinese Yuan (CNY)',
                'SGD' => 'Singapore Dollar (SGD)',
                'CHF' => 'Swiss Franc (CHF)',
            ];
        }
    }
}
