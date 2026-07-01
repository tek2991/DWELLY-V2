<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\Bills\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Models\Bill;

class BillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bill_number')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('contact.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('issue_date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (Bill $record) => $record->display_status)
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'received' => 'info',
                        'partially_paid' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('grand_total')
                    ->money(fn ($record) => $record->currency_code ?? config('accounting.default_currency', 'USD'))
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('balance_due')
                    ->money(fn ($record) => $record->currency_code ?? config('accounting.default_currency', 'USD'))
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(BillStatus::class),
                Tables\Filters\Filter::make('overdue')
                    ->query(fn ($query) => $query->where('balance_due', '>', 0)->where('due_date', '<', now())),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn (Bill $record) => $record->status === BillStatus::Draft),
                Actions\Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (Bill $record) => $record->status === BillStatus::Draft)
                    ->action(function (Bill $record) {
                        try {
                            app(\Tek2991\Accounting\Services\BillService::class)->post($record);
                            \Filament\Notifications\Notification::make()->title('Bill posted successfully')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Failed to post bill')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->groupedBulkActions([
                Actions\BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $skipped = 0;
                        foreach ($records as $record) {
                            if ($record->status === BillStatus::Draft) {
                                $record->delete();
                            } else {
                                $skipped++;
                            }
                        }
                        
                        if ($skipped > 0) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title("{$skipped} posted document(s) could not be deleted.")
                                ->body('Posted documents are immutable and must be cancelled instead.')
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
