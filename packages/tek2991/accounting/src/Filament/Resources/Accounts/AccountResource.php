<?php

namespace Tek2991\Accounting\Filament\Resources\Accounts;

use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Tek2991\Accounting\Filament\Resources\Accounts\Pages;
use Tek2991\Accounting\Filament\Resources\Accounts\Schemas\AccountForm;
use Tek2991\Accounting\Filament\Resources\Accounts\Tables\AccountsTable;
use Tek2991\Accounting\Models\Account;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static \UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Chart of Accounts';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return AccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
