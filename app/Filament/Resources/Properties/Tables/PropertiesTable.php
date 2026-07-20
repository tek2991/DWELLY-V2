<?php

namespace App\Filament\Resources\Properties\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('building_name')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->badge(),
                \Filament\Tables\Columns\ToggleColumn::make('is_listed')
                    ->label('Listed')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                \Filament\Actions\Action::make('onboarding')
                    ->label('Onboarding')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->hidden(fn (\App\Domain\Property\Models\Property $record): bool => $record->onboardingProject?->status === 'Activated')
                    ->url(fn (\App\Domain\Property\Models\Property $record): string => \App\Filament\Resources\Properties\PropertyResource::getUrl('onboarding', ['record' => $record])),
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
