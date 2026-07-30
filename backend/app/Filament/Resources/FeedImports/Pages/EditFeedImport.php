<?php

namespace App\Filament\Resources\FeedImports\Pages;

use App\Domain\Feeds\FeedImportWorkflow;
use App\Filament\Resources\FeedImports\FeedImportResource;
use App\Models\FeedImport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditFeedImport extends EditRecord
{
    protected static string $resource = FeedImportResource::class;

    public function getHeading(): string
    {
        return 'Datu importa pārskats';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apply')
                ->label('Importēt')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Importēt pārbaudītos ierakstus?')
                ->modalDescription('Piedāvājumi un izmēri tiks atjaunināti vienā darbībā.')
                ->modalSubmitActionLabel('Importēt')
                ->visible(fn (): bool => $this->record->status === FeedImport::STATUS_READY)
                ->disabled(fn (): bool => ! $this->record->canApply())
                ->tooltip(fn (): ?string => $this->record->canApply()
                    ? null
                    : 'Pārbaudi vai ignorē visus atzīmētos ierakstus.')
                ->action(function (): void {
                    try {
                        app(FeedImportWorkflow::class)->apply($this->record);
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title('Importu neizdevās pabeigt')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Dati importēti')
                        ->success()
                        ->send();

                    $this->redirect(FeedImportResource::getUrl('edit', [
                        'record' => $this->record,
                    ]));
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
