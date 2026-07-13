<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Livewire\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MappedDocumentsRelationManager extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public Model $ownerRecord;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return Tab::make('Mapped Documents')
            ->icon('heroicon-o-document-text');
    }

    public static function getDefaultProperties(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $mouId = $this->ownerRecord->mou_id;
                
                $query = Media::query();
                
                if ($mouId) {
                    $query->where('model_type', \App\Domain\Mou\Models\Mou::class)
                          ->where('model_id', $mouId);
                } else {
                    $query->where('id', 0); // Empty query if no MOU
                }
                
                return $query;
            })
            ->columns([
                TextColumn::make('collection_name')
                    ->label('Document Type')
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'signed_pdf' => 'Signed MOU',
                        'draft_pdf' => 'Draft MOU',
                        'archived_signed_pdf' => 'Archived MOU',
                        default => str($state)->headline(),
                    })
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'signed_pdf' => 'success',
                        'draft_pdf' => 'warning',
                        'archived_signed_pdf' => 'gray',
                        default => 'primary',
                    }),
                TextColumn::make('file_name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('View Document')
                    ->modalWidth('7xl')
                    ->modalSubmitActionLabel('Download Document')
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (Media $record) {
                        return view('components.pdf-viewer-raw', [
                            'path' => $record->getPath()
                        ]);
                    })
                    ->action(function (Media $record) {
                        return response()->download($record->getPath(), $record->file_name);
                    }),
                    
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Media $record) {
                        return response()->download($record->getPath(), $record->file_name);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function render()
    {
        return view('filament.properties.documents');
    }
}
