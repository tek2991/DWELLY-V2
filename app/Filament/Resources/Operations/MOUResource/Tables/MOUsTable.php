<?php

namespace App\Filament\Resources\Operations\MOUResource\Tables;

use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Mou\Services\MouWorkflowService;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Property\Services\PropertyOnboardingService;
use App\Domain\Shared\Enums\DocumentType;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class MOUsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('MOU #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('MOU number copied')
                    ->tooltip('Click to copy MOU #'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('opportunity.title')
                    ->label('Opportunity / Lead')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Mou $record) => $record->party?->display_name ? "👤 {$record->party->display_name}" : ($record->owner_name ? "👤 {$record->owner_name}" : null)),

                TextColumn::make('party.display_name')
                    ->label('Party / Owner')
                    ->placeholder('Unresolved')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('legal_terms.rent_amount')
                    ->label('Agreed Rent')
                    ->money('INR')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('type')
                    ->options(MouType::class),
                SelectFilter::make('status')
                    ->options(MouStatus::class),
            ])
            ->actions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn ($record) => MOUResource::canEdit($record)),

                    Action::make('uploadDocument')
                        ->label('Upload Documents')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('primary')
                        ->visible(fn (Mou $record) => MOUResource::canEdit($record))
                        ->form([
                            Select::make('document_type')
                                ->label('Document Type')
                                ->options(DocumentType::class)
                                ->required()
                                ->searchable(),
                            FileUpload::make('files')
                                ->label('Files (Images / PDF)')
                                ->multiple()
                                ->preserveFilenames()
                                ->required(),
                        ])
                        ->action(function (Mou $record, array $data) {
                            $collection = match ($data['document_type']) {
                                'aadhaar', 'owner_aadhaar' => 'owner_aadhaar',
                                'pan', 'owner_pan' => 'owner_pan',
                                'cancelled_cheque' => 'cancelled_cheque',
                                'electricity_bill' => 'electricity_bill',
                                'power_of_attorney' => 'signatory_poa',
                                default => 'mou_attachments',
                            };

                            foreach ($data['files'] as $path) {
                                $fullPath = Storage::disk(config('filament.default_filesystem_disk'))->path($path);
                                if (! file_exists($fullPath)) {
                                    $fullPath = Storage::disk('public')->path($path);
                                }

                                $record->addMedia($fullPath)
                                    ->withCustomProperties([
                                        'document_type' => $data['document_type'],
                                    ])
                                    ->toMediaCollection($collection);
                            }

                            $record->refresh();
                            Notification::make()->title('Document Uploaded Successfully')->success()->send();
                        }),

                    MOUResource::getResolvePartyAction(),
                    MOUResource::getUpdatePartyAction(),

                    MOUResource::getProvisionAccountingAction(),
                    MOUResource::getGeneratePdfAction(),
                    MOUResource::getUploadSignedCopyAction(),
                    MOUResource::getVerifyAction(),
                    MOUResource::getConvertToPropertyAction(),
                    MOUResource::getArchiveAction(),
                ])
                ->label('Actions')
                ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->bulkActions([
                BulkActionGroup::make([]),
            ]);
    }
}
