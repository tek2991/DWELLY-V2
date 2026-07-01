<?php

namespace Tek2991\Accounting\Filament\Resources\Banking;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\BankAccountType;
use Tek2991\Accounting\Filament\Resources\Banking\Pages;
use Tek2991\Accounting\Filament\Resources\Banking\Schemas\BankAccountForm;
use Tek2991\Accounting\Filament\Resources\Banking\Tables\BankAccountsTable;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\BankAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static ?string $modelLabel = 'Bank Account';

    protected static ?string $pluralModelLabel = 'Bank Accounts';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Accounts';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return BankAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit'   => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}
