<?php

namespace App\Filament\Resources\CommunicationLogs;

use App\Domain\Communication\Models\CommunicationLog;
use App\Filament\Resources\CommunicationLogs\Pages\ListCommunicationLogs;
use App\Filament\Resources\CommunicationLogs\Schemas\CommunicationLogForm;
use App\Filament\Resources\CommunicationLogs\Tables\CommunicationLogsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommunicationLogResource extends Resource
{
    protected static ?string $model = CommunicationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Sales & CRM';


    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CommunicationLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunicationLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunicationLogs::route('/'),
            // No create or edit pages — this is a read-only audit log
        ];
    }
}
