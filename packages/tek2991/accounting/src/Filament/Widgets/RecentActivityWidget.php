<?php

namespace Tek2991\Accounting\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;

class RecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 8;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Recent Activity';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->whereIn('log_name', ['accounting', 'financial'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Event')
                    ->formatStateUsing(function ($state, $record) {
                        // Some logs might just be "created". Let's enhance it with the subject type if it's a simple string.
                        if (in_array($state, ['created', 'updated', 'deleted'])) {
                            $subjectType = class_basename($record->subject_type);
                            return "{$subjectType} {$state}";
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User'),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5);
    }
}
