<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\Bills;

use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Filament\Resources\Purchases\Bills\Pages;
use Tek2991\Accounting\Filament\Resources\Purchases\Bills\Schemas\BillForm;
use Tek2991\Accounting\Filament\Resources\Purchases\Bills\Tables\BillsTable;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;
    
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static \UnitEnum|string|null $navigationGroup = 'Purchases';
    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return BillForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBills::route('/'),
            'create' => Pages\CreateBill::route('/create'),
            'view' => Pages\ViewBill::route('/{record}'),
            'edit' => Pages\EditBill::route('/{record}/edit'),
        ];
    }
}
