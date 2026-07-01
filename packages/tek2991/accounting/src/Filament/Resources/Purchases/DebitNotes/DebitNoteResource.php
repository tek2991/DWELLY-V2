<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes;

use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Tek2991\Accounting\Models\DebitNote;
use Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\Schemas\DebitNoteForm;
use Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\Pages;

class DebitNoteResource extends Resource
{
    protected static ?string $model = DebitNote::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-minus';
    protected static \UnitEnum|string|null $navigationGroup = 'Purchases';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(DebitNoteForm::make());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('debit_note_number')->searchable()->sortable(),
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
                    ->visible(fn (\Tek2991\Accounting\Models\DebitNote $record) => $record->status === \Tek2991\Accounting\Enums\DebitNoteStatus::Draft),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDebitNotes::route('/'),
            'create' => Pages\CreateDebitNote::route('/create'),
            'edit' => Pages\EditDebitNote::route('/{record}/edit'),
            'view' => Pages\ViewDebitNote::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        app(\Tek2991\Accounting\Services\BranchContext::class)->applyQueryScope($query);
        return $query;
    }
}
