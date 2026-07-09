<?php

namespace App\Filament\Resources\Property\AmenityTypes;

use App\Domain\Property\Models\Property\AmenityType;
use App\Filament\Resources\Property\AmenityTypes\Pages\CreateAmenityType;
use App\Filament\Resources\Property\AmenityTypes\Pages\EditAmenityType;
use App\Filament\Resources\Property\AmenityTypes\Pages\ListAmenityTypes;
use App\Filament\Resources\Property\AmenityTypes\Schemas\AmenityTypeForm;
use App\Filament\Resources\Property\AmenityTypes\Tables\AmenityTypesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AmenityTypeResource extends Resource
{
    protected static ?string $model = AmenityType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AmenityTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AmenityTypesTable::configure($table);
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
            'index' => ListAmenityTypes::route('/'),
            'create' => CreateAmenityType::route('/create'),
            'edit' => EditAmenityType::route('/{record}/edit'),
        ];
    }
}
