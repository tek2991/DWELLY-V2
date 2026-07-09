<?php

namespace App\Filament\Resources\Property\InventoryTypes;

use App\Domain\Property\Models\Property\InventoryType;
use App\Filament\Resources\Property\InventoryTypes\Pages\CreateInventoryType;
use App\Filament\Resources\Property\InventoryTypes\Pages\EditInventoryType;
use App\Filament\Resources\Property\InventoryTypes\Pages\ListInventoryTypes;
use App\Filament\Resources\Property\InventoryTypes\Schemas\InventoryTypeForm;
use App\Filament\Resources\Property\InventoryTypes\Tables\InventoryTypesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventoryTypeResource extends Resource
{
    protected static ?string $model = InventoryType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return InventoryTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryTypesTable::configure($table);
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
            'index' => ListInventoryTypes::route('/'),
            'create' => CreateInventoryType::route('/create'),
            'edit' => EditInventoryType::route('/{record}/edit'),
        ];
    }
}
