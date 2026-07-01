<?php

namespace Tek2991\Accounting\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Filament\Resources\Sales\Invoices\InvoiceResource;
use Tek2991\Accounting\ValueObjects\Money;

class OutstandingReceivablesWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;
    protected static ?string $heading = 'Outstanding Receivables';

    public function table(Table $table): Table
    {
        $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId();
        
        return $table
            ->query(
                Invoice::query()
                    ->with('contact')
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('balance_due', '>', 0)
                    ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            )
            ->columns([
                Tables\Columns\TextColumn::make('contact.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record) => (new Money($record->getRawOriginal('balance_due'), $record->currency_code))->format())
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Age')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        $days = $state->diffInDays(now(), false);
                        if ($days < 0) return abs((int)$days) . ' days to go';
                        if ($days == 0) return 'Due today';
                        return (int)$days . ' days overdue';
                    })
                    ->color(function ($state) {
                        if (!$state) return 'gray';
                        $days = $state->diffInDays(now(), false);
                        return $days > 0 ? 'danger' : 'gray';
                    })
                    ->sortable(),
            ])
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->label('View')
                    ->url(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5);
    }
}
