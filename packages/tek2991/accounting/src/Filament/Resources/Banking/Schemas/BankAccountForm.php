<?php

namespace Tek2991\Accounting\Filament\Resources\Banking\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Tek2991\Accounting\Enums\BankAccountType;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\BankAccount;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Account Information')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Account Type')
                        ->options(BankAccountType::class)
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, $state) {
                            $set('account_id', null);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Select::make('account_id')
                        ->label('Link to Chart Account')
                        ->helperText('Select the chart account this bank account represents. Each bank account must map to its own unique chart account. If you don\'t see the option, you need to go to the Chart of Accounts and create a new account first (e.g., "HDFC Checking").')
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $typeValue = $get('type');
                            if (! $typeValue) {
                                return [];
                            }

                            $bankType = $typeValue instanceof BankAccountType ? $typeValue : BankAccountType::from($typeValue);
                            
                            $query = Account::query()->active()->isNotBankAccount();

                            $query->whereIn('reporting_class', $bankType->getValidReportingClasses());

                            // We only allow linking to accounts with Bank or Cash roles for Depository, or specific roles.
                            // But let's just let them pick from the valid reporting class to be safe.
                            
                            return $query->pluck('name', 'id');
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => Account::find($value)?->name)
                        ->searchable()
                        ->required()
                        ->disabledOn('edit')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('nickname')
                        ->label('Nickname')
                        ->helperText('A friendly name for this bank account (e.g. "Primary Checking" or "Corporate Visa").')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('number')
                        ->label('Account Number')
                        ->helperText('Last 4 digits will be shown as a mask (e.g. •••• 1234).')
                        ->maxLength(30)
                        ->nullable(),

                    Forms\Components\Toggle::make('enabled')
                        ->label('Set as Default')
                        ->helperText('Mark this as the default bank account for this type.')
                        ->default(false),
                ]),
        ]);
    }
}
