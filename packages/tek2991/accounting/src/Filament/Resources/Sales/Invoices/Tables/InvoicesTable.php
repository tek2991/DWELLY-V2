<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\Invoices\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Invoice;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('contact.name')
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
                    ->getStateUsing(fn (Invoice $record) => $record->display_status)
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
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
                    ->options(InvoiceStatus::class),
                Tables\Filters\Filter::make('overdue')
                    ->query(fn ($query) => $query->where('balance_due', '>', 0)->where('due_date', '<', now())),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft),
                Actions\Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft)
                    ->action(function (Invoice $record) {
                        app(\Tek2991\Accounting\Services\InvoiceService::class)->post($record);
                        // Using Filament Notification to inform user
                        \Filament\Notifications\Notification::make()->title('Invoice posted successfully')->success()->send();
                    }),
                Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function (Invoice $record) {
                        $path = app(\Tek2991\Accounting\Services\InvoiceService::class)->generatePdf($record);
                        $disk = config('accounting.pdf.disk', 'public');
                        return response()->download(\Illuminate\Support\Facades\Storage::disk($disk)->path($path));
                    }),
                Actions\Action::make('download_receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-receipt-percent')
                    ->color('success')
                    ->visible(fn (Invoice $record) => $record->payments()->exists())
                    ->url(fn (Invoice $record) => route('billing.receipt.pdf', [
                        'invoice' => $record->id,
                        'payment' => $record->payments()->latest()->first()?->id ?? 0,
                    ]), shouldOpenInNewTab: true),
                Actions\Action::make('email')
                    ->label('Email')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record) => $record->status !== InvoiceStatus::Draft)
                    ->action(function (Invoice $record) {
                        $contactEmail = $record->contact->email ?? null;
                        if (!$contactEmail) {
                            \Filament\Notifications\Notification::make()->title('Contact has no email')->danger()->send();
                            return;
                        }
                        
                        $path = app(\Tek2991\Accounting\Services\InvoiceService::class)->generatePdf($record);
                        
                        \Illuminate\Support\Facades\Mail::to($contactEmail)
                            ->send(new \Tek2991\Accounting\Mail\InvoiceMail($record, $path));
                            
                        \Filament\Notifications\Notification::make()->title('Invoice emailed successfully')->success()->send();
                    }),
            ])
            ->groupedBulkActions([
                Actions\BulkAction::make('post_selected')
                    ->label('Post Selected')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Post Selected Invoices')
                    ->modalDescription('Are you sure you want to approve and post all selected draft invoices to the General Ledger?')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $invoiceService = app(\Tek2991\Accounting\Services\InvoiceService::class);
                        $posted = 0;
                        $skipped = 0;
                        $errors = [];

                        foreach ($records as $record) {
                            if ($record->status === InvoiceStatus::Draft) {
                                try {
                                    $invoiceService->post($record);
                                    $posted++;
                                } catch (\Throwable $e) {
                                    $errors[] = "#{$record->invoice_number}: " . $e->getMessage();
                                }
                            } else {
                                $skipped++;
                            }
                        }

                        if ($posted > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title("{$posted} Invoice(s) posted successfully")
                                ->success()
                                ->send();
                        }

                        if (!empty($errors)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Some invoices could not be posted')
                                ->body(implode('<br>', $errors))
                                ->danger()
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
                Actions\BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $skipped = 0;
                        foreach ($records as $record) {
                            if ($record->status === InvoiceStatus::Draft) {
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
