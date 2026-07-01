<?php

namespace Tek2991\Accounting\Filament\Resources\Items;

use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Item;
use Tek2991\Accounting\Filament\Resources\Items\Pages;
use Tek2991\Accounting\Filament\Resources\Items\Schemas\ItemForm;
use Tek2991\Accounting\Filament\Resources\Items\Tables\ItemsTable;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-archive-box';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';
    
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // We can add Spatie activitylog relation manager here later
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
