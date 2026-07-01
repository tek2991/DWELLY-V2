<?php

namespace Tek2991\Accounting\Filament\Exports;

use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Tek2991\Accounting\Models\Transaction;

class TransactionExporter extends Exporter
{
    protected static ?string $model = Transaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('posted_at')
                ->label('Date'),
            ExportColumn::make('type')
                ->label('Type'),
            ExportColumn::make('bankAccount.account.name')
                ->label('Bank Account'),
            ExportColumn::make('reference')
                ->label('Reference'),
            ExportColumn::make('description')
                ->label('Description'),
            ExportColumn::make('amount')
                ->label('Amount'),
            ExportColumn::make('reviewed')
                ->label('Reviewed'),
            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your transaction export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
