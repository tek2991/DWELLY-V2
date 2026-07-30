<?php

namespace App\Filament\Resources\TenancyAgreements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use App\Domain\Agreement\Services\TenancyAgreementPdfService;
use App\Domain\Agreement\Services\TenancyAgreementDocxService;

class TenancyAgreementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Agreement Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('property.building_name')
                    ->label('Property')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('audit.audit_number')
                    ->label('Linked Audit')
                    ->placeholder('None')
                    ->sortable(),

                TextColumn::make('rent_amount')
                    ->label('Rent (₹)')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('security_deposit')
                    ->label('Deposit (₹)')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'signed' => 'info',
                        'active' => 'success',
                        'terminated' => 'danger',
                        default => 'primary',
                    })
                    ->sortable(),

                IconColumn::make('keys_handed_over')
                    ->label('Keys Handed Over')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('download_pdf')
                    ->label('Draft PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(function ($record, TenancyAgreementPdfService $pdfService) {
                        $binary = $pdfService->generatePdf($record);
                        $filename = 'Tenancy_Agreement_Draft_' . ($record->code ?? $record->id) . '.pdf';
                        return response()->streamDownload(fn() => print($binary), $filename, [
                            'Content-Type' => 'application/pdf',
                        ]);
                    }),

                Action::make('download_docx')
                    ->label('Draft Word')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->action(function ($record, TenancyAgreementDocxService $docxService) {
                        $binary = $docxService->generateDocx($record);
                        $filename = 'Tenancy_Agreement_Draft_' . ($record->code ?? $record->id) . '.docx';
                        return response()->streamDownload(fn() => print($binary), $filename, [
                            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ]);
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
