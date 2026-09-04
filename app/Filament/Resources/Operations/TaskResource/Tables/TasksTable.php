<?php

namespace App\Filament\Resources\Operations\TaskResource\Tables;

use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Services\TaskService;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('task_number')
                    ->label('Task #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Task number copied'),

                TextColumn::make('property.code')
                    ->label('Property')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Task $record) => $record->property?->building_name ?? $record->property?->city),

                TextColumn::make('title')
                    ->label('Task Title')
                    ->searchable()
                    ->limit(35)
                    ->tooltip(fn (Task $record) => $record->title),

                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('checklist_progress')
                    ->label('Checklist')
                    ->badge()
                    ->color(function (string $state) {
                        if ($state === 'N/A') {
                            return 'gray';
                        }
                        [$done, $total] = explode('/', $state);
                        return ((int) $done === (int) $total) ? 'success' : 'warning';
                    }),

                TextColumn::make('assignedTo.name')
                    ->label('Assigned Staff')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->color(fn (Task $record) => $record->is_overdue ? 'danger' : 'gray')
                    ->description(function (Task $record) {
                        if ($record->is_overdue) {
                            return '⚠️ Overdue';
                        }
                        return null;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(TaskStatus::class),

                SelectFilter::make('category')
                    ->options(TaskCategory::class),

                SelectFilter::make('priority')
                    ->options(TaskPriority::class),

                SelectFilter::make('assigned_to_id')
                    ->label('Assigned Staff')
                    ->options(fn () => User::where('is_active', true)->pluck('name', 'id')),

                SelectFilter::make('property_id')
                    ->label('Property')
                    ->relationship('property', 'code')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('overdue')
                    ->label('Overdue Tasks Only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value])
                            ->whereNotNull('due_date')
                            ->where('due_date', '<', now()),
                        false: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('markCompleted')
                    ->label('Complete')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn (Task $record) => ! in_array($record->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED]))
                    ->form([
                        Textarea::make('resolution_notes')
                            ->label('Resolution Notes / Work Summary')
                            ->required()
                            ->rows(3)
                            ->placeholder('Describe what was done, key observations, or verification details...'),
                    ])
                    ->action(function (Task $record, array $data) {
                        try {
                            app(TaskService::class)->completeTask($record, $data['resolution_notes']);
                            Notification::make()
                                ->title('Task Marked Complete')
                                ->success()
                                ->send();
                        } catch (\Illuminate\Validation\ValidationException $e) {
                            Notification::make()
                                ->title('Cannot Complete Task')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('quickAssign')
                    ->label('Assign')
                    ->icon('heroicon-m-user-plus')
                    ->color('info')
                    ->form([
                        Select::make('assigned_to_id')
                            ->label('Assign to Staff Member')
                            ->options(fn () => User::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (Task $record, array $data) {
                        $record->update([
                            'assigned_to_id' => $data['assigned_to_id'],
                            'status' => $record->status === TaskStatus::PENDING ? TaskStatus::SCHEDULED : $record->status,
                        ]);
                        Notification::make()
                            ->title('Task Assigned Successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
