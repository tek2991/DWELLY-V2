<?php

namespace Tek2991\Accounting\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Filament\Resources\Purchases\Bills\BillResource;
use Tek2991\Accounting\ValueObjects\Money;

class OutstandingPayablesWidget extends BaseWidget
{
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 1;
    protected static ?string $heading = 'Outstanding Payables';

    public function table(Table $table): Table
    {
        $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId();
        
        return $table
            ->query(
                Bill::query()
                    ->with('contact')
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('balance_due', '>', 0)
                    ->whereNotIn('status', [BillStatus::Draft, BillStatus::Cancelled])
            )
            ->columns([
                Tables\Columns\TextColumn::make('contact.name')
                    ->label('Vendor')
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
                    ->url(fn (Bill $record): string => BillResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5);
    }
}
