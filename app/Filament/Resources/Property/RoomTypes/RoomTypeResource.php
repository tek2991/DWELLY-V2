<?php

namespace App\Filament\Resources\Property\RoomTypes;

use App\Domain\Property\Models\Property\RoomType;
use App\Filament\Resources\Property\RoomTypes\Pages\CreateRoomType;
use App\Filament\Resources\Property\RoomTypes\Pages\EditRoomType;
use App\Filament\Resources\Property\RoomTypes\Pages\ListRoomTypes;
use App\Filament\Resources\Property\RoomTypes\Schemas\RoomTypeForm;
use App\Filament\Resources\Property\RoomTypes\Tables\RoomTypesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RoomTypeResource extends Resource
{
    protected static ?string $model = RoomType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RoomTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomTypesTable::configure($table);
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
            'index' => ListRoomTypes::route('/'),
            'create' => CreateRoomType::route('/create'),
            'edit' => EditRoomType::route('/{record}/edit'),
        ];
    }
}
