<?php

namespace Tek2991\Accounting\Filament\Resources\Transactions;

use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Filament\Resources\Transactions\Pages;
use Tek2991\Accounting\Filament\Resources\Transactions\Tables\TransactionsTable;
use Tek2991\Accounting\Models\Transaction;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTransactions::route('/'),
            'view'   => Pages\ViewTransaction::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        app(\Tek2991\Accounting\Services\BranchContext::class)->applyQueryScope($query);
        return $query;
    }
}
