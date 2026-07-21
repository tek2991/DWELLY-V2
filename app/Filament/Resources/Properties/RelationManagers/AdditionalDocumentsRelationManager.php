<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Livewire\Component;
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
use Filament\Actions\DeleteAction;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Storage;

class AdditionalDocumentsRelationManager extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public Model $ownerRecord;

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $relevantMou = $this->ownerRecord->mous()
                    ->whereIn('type', [
                        \App\Domain\Mou\Enums\MouType::ONBOARDING, 
                        \App\Domain\Mou\Enums\MouType::KYC_UPDATE,
                        \App\Domain\Mou\Enums\MouType::BANK_DETAILS_UPDATE
                    ])
                    ->latest()
                    ->first();
                $targetModel = $relevantMou ?? $this->ownerRecord;
                
                return Media::query()
                    ->where('model_type', get_class($targetModel))
                    ->where('model_id', $targetModel->id)
                    ->whereIn('collection_name', ['mou_attachments', 'signatory_documents']);
            })
            ->columns([
                TextColumn::make('collection_name')
                    ->label('Document Category')
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'mou_attachments' => 'Owner KYC & Cancelled Cheque',
                        'signatory_documents' => 'Signatory Authorization & KYC',
                        default => str($state)->headline(),
                    })
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'mou_attachments' => 'primary',
                        'signatory_documents' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('file_name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('addDocument')
                    ->label('Upload Document')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        \Filament\Forms\Components\Select::make('collection_name')
                            ->label('Document Category')
                            ->options([
                                'mou_attachments' => 'Owner KYC & Cancelled Cheque',
                                'signatory_documents' => 'Signatory Authorization & KYC',
                            ])
                            ->required(),
                        \Filament\Forms\Components\FileUpload::make('files')
                            ->label('Files (Images/PDFs)')
                            ->multiple()
                            ->preserveFilenames()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $relevantMou = $this->ownerRecord->mous()
                            ->whereIn('type', [
                                \App\Domain\Mou\Enums\MouType::ONBOARDING, 
                                \App\Domain\Mou\Enums\MouType::KYC_UPDATE,
                                \App\Domain\Mou\Enums\MouType::BANK_DETAILS_UPDATE
                            ])
                            ->latest()
                            ->first();
                        $targetModel = $relevantMou ?? $this->ownerRecord;
                        
                        foreach ($data['files'] as $path) {
                            $targetModel->addMedia(Storage::disk('public')->path($path))
                                ->toMediaCollection($data['collection_name']);
                        }
                    })
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
                    
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function render()
    {
        return view('filament.properties.documents');
    }
}
