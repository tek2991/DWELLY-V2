<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\CreditNotes;

use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Tek2991\Accounting\Models\CreditNote;
use Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\Schemas\CreditNoteForm;
use Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\Pages;

class CreditNoteResource extends Resource
{
    protected static ?string $model = CreditNote::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-minus';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(CreditNoteForm::make());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('credit_note_number')->searchable()->sortable(),
                TextColumn::make('contact.name')->searchable()->sortable(),
                TextColumn::make('issue_date')->date()->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'issued',
                        'success' => 'applied',
                        'danger' => 'cancelled',
                    ]),
                TextColumn::make('grand_total')
                    ->money()
                    ->sortable(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make()
                    ->visible(fn (\Tek2991\Accounting\Models\CreditNote $record) => $record->status === \Tek2991\Accounting\Enums\CreditNoteStatus::Draft),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreditNotes::route('/'),
            'create' => Pages\CreateCreditNote::route('/create'),
            'edit' => Pages\EditCreditNote::route('/{record}/edit'),
            'view' => Pages\ViewCreditNote::route('/{record}'),
        ];
    }
}
