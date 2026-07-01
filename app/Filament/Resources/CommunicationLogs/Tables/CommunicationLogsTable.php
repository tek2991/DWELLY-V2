<?php

namespace App\Filament\Resources\CommunicationLogs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class CommunicationLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('party.display_name')->label('Recipient Name')->searchable(),
                TextColumn::make('channel')->badge()->color(fn (string $state): string => match ($state) {
                    'email' => 'primary',
                    'whatsapp' => 'success',
                    'sms' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('recipient')->label('Contact Details')->searchable(),
                TextColumn::make('subject')->limit(30),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'delivered' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sent_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // Read-only
            ])
            ->toolbarActions([
                // Read-only
            ]);
    }
}
