<?php

namespace App\Filament\Widgets;

use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Filament\Resources\Operations\TaskResource;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingTasksWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Task::query()
                    ->whereNotIn('status', [
                        TaskStatus::COMPLETED,
                        TaskStatus::CANCELLED,
                    ])
                    ->with(['property', 'assignedTo'])
                    ->orderByRaw("CASE 
                        WHEN due_date IS NOT NULL AND due_date < '" . now()->toDateTimeString() . "' THEN 1 
                        WHEN priority = 'urgent' THEN 2 
                        WHEN priority = 'high' THEN 3 
                        ELSE 4 
                    END")
                    ->latest('updated_at')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('task_number')
                    ->label('Task')
                    ->description(fn (Task $record): ?string => $record->property?->building_name ?? $record->property?->title)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Work')
                    ->limit(18)
                    ->searchable(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->dateTime('d M')
                    ->color(fn ($state) => $state && \Carbon\Carbon::parse($state)->isPast() ? 'danger' : 'gray'),
            ])
            ->actions([
                Action::make('manage')
                    ->label('Manage')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (Task $record): string => TaskResource::getUrl('edit', ['record' => $record])),
            ])
            ->heading('Pending Works & Operational Tasks')
            ->emptyStateHeading('No Pending Tasks')
            ->emptyStateDescription('All field works, compliance, and turnover tasks are up to date.');
    }
}
