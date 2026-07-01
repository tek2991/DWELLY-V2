<?php

namespace Tek2991\Accounting\Filament\Resources\Accounts\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Models\Account;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent Account')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Category')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reporting_class')
                    ->label('Reporting Class')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('system_role')
                    ->label('System Role')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_control_account')
                    ->label('Control')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('currency_code')
                    ->label('Currency')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('archived')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->label('Archived')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('default')
                    ->boolean()
                    ->label('Default')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Category')
                    ->options(AccountType::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('reporting_class')
                    ->options(ReportingClass::class)
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_control_account')
                    ->label('Control Account'),

                Tables\Filters\TernaryFilter::make('archived')
                    ->label('Status')
                    ->trueLabel('Archived')
                    ->falseLabel('Active')
                    ->placeholder('All'),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('archive')
                    ->label(fn (Account $record) => $record->archived ? 'Restore' : 'Archive')
                    ->icon(fn (Account $record) => $record->archived ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-archive-box')
                    ->color(fn (Account $record) => $record->archived ? 'success' : 'warning')
                    ->requiresConfirmation()
                    ->action(fn (Account $record) => $record->update(['archived' => ! $record->archived])),
            ])
            ->groupedBulkActions([
                Actions\DeleteBulkAction::make(),
                Actions\BulkAction::make('archive')
                    ->label('Archive Selected')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['archived' => true])),
            ])
            ->groups([
                Tables\Grouping\Group::make('type')
                    ->label('Category')
                    ->collapsible(),
                Tables\Grouping\Group::make('reporting_class')
                    ->label('Reporting Class')
                    ->collapsible(),
            ]);
    }
}
