<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Task\Models\Task;
use App\Filament\Resources\Operations\TaskResource\Pages\CreateTask;
use App\Filament\Resources\Operations\TaskResource\Pages\EditTask;
use App\Filament\Resources\Operations\TaskResource\Pages\ListTasks;
use App\Filament\Resources\Operations\TaskResource\Schemas\TaskForm;
use App\Filament\Resources\Operations\TaskResource\Tables\TasksTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Portfolio & Operations';

    protected static ?string $navigationLabel = 'Operations Tasks';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
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
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
