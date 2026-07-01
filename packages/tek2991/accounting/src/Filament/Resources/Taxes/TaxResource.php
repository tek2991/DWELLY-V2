<?php

namespace Tek2991\Accounting\Filament\Resources\Taxes;

use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Tax;
use Tek2991\Accounting\Filament\Resources\Taxes\Pages;
use Tek2991\Accounting\Filament\Resources\Taxes\Schemas\TaxForm;
use Tek2991\Accounting\Filament\Resources\Taxes\Tables\TaxesTable;

class TaxResource extends Resource
{
    protected static ?string $model = Tax::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 3;
    
    protected static ?string $navigationLabel = 'Taxes';

    public static function form(Schema $schema): Schema
    {
        return TaxForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxesTable::configure($table);
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
            'index' => Pages\ListTaxes::route('/'),
            'create' => Pages\CreateTax::route('/create'),
            'edit' => Pages\EditTax::route('/{record}/edit'),
        ];
    }
}
