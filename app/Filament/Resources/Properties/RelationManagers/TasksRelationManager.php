<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Services\TaskService;
use App\Filament\Resources\Operations\TaskResource;
use App\Filament\Resources\Operations\TaskResource\Schemas\TaskForm;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Operations Tasks';

    protected static \BackedEnum|string|null $icon = Heroicon::OutlinedClipboardDocumentCheck;

    public function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('task_number')
                    ->label('Task #')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('category')
                    ->badge(),

                TextColumn::make('priority')
                    ->badge(),

                TextColumn::make('status')
                    ->badge(),

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
                    ->placeholder('Unassigned'),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->dateTime('d M Y')
                    ->color(fn (Task $record) => $record->is_overdue ? 'danger' : 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(TaskStatus::class),
                SelectFilter::make('category')
                    ->options(TaskCategory::class),
                SelectFilter::make('priority')
                    ->options(TaskPriority::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['created_by_id'] = auth()->id();
                        $data['status'] = $data['status'] ?? TaskStatus::PENDING->value;
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (Task $record) => TaskResource::getUrl('edit', ['record' => $record])),

                Action::make('markCompleted')
                    ->label('Complete')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn (Task $record) => ! in_array($record->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED]))
                    ->form([
                        Textarea::make('resolution_notes')
                            ->label('Resolution Summary & Notes')
                            ->required()
                            ->rows(3),
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
            ]);
    }
}
