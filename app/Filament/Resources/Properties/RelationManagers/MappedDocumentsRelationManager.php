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
                $mouIds = $this->ownerRecord->mous()->pluck('id')->toArray();
                
                return Media::query()
                    ->where(function ($query) use ($mouIds) {
                        if (!empty($mouIds)) {
                            $query->where(function ($sub) use ($mouIds) {
                                $sub->where('model_type', \App\Domain\Mou\Models\Mou::class)
                                    ->whereIn('model_id', $mouIds);
                            });
                        }
                        $query->orWhere(function ($sub) {
                            $sub->where('model_type', get_class($this->ownerRecord))
                                ->where('model_id', $this->ownerRecord->id);
                        });
                    });
            })
            ->columns([
                TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(function (Media $record) {
                        if ($record->model instanceof \App\Domain\Mou\Models\Mou) {
                            $type = $record->model->type;
                            return ($type instanceof \App\Domain\Mou\Enums\MouType ? $type->label() : str($type)->headline()) . ' (#' . $record->model->number . ')';
                        }
                        return 'Property Profile';
                    })
                    ->badge(),
                TextColumn::make('collection_name')
                    ->label('Document Type')
                    ->formatStateUsing(function (string $state, Media $record) {
                        $docTypeVal = $record->getCustomProperty('document_type');
                        if ($docTypeVal) {
                            $enumLabel = \App\Domain\Shared\Enums\DocumentType::tryFrom($docTypeVal)?->getLabel();
                            if ($enumLabel) return $enumLabel;
                        }
                        return match($state) {
                            'signed_pdf' => 'Signed MOU',
                            'draft_pdf' => 'Draft MOU',
                            'archived_signed_pdf' => 'Archived MOU',
                            'owner_aadhaar' => 'Owner Aadhaar Card',
                            'owner_pan' => 'Owner PAN Card',
                            'cancelled_cheque' => 'Cancelled Cheque',
                            'electricity_bill' => 'Electricity Bill',
                            'signatory_aadhaar' => 'Signatory Aadhaar Card',
                            'signatory_pan' => 'Signatory PAN Card',
                            'signatory_poa' => 'Power of Attorney',
                            'mou_attachments' => 'Owner Attachment',
                            'signatory_documents' => 'Signatory Attachment',
                            default => str($state)->headline(),
                        };
                    })
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'signed_pdf', 'owner_aadhaar', 'owner_pan' => 'success',
                        'draft_pdf', 'signatory_aadhaar', 'signatory_pan', 'signatory_poa' => 'warning',
                        'cancelled_cheque', 'electricity_bill' => 'info',
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
                        return view('components.document-viewer-raw', [
                            'path' => $record->getPath(),
                            'mimeType' => $record->mime_type,
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
