<?php

namespace App\Filament\Resources\FeedImports\Pages;

use App\Domain\Feeds\FeedImportQueue;
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
        return 'Feed import review';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshStatus')
                ->label('Refresh status')
                ->visible(fn (): bool => in_array($this->record->status, [
                    FeedImport::STATUS_PREVIEW_QUEUED,
                    FeedImport::STATUS_PREVIEWING,
                    FeedImport::STATUS_APPLY_QUEUED,
                    FeedImport::STATUS_APPLYING,
                ], true))
                ->action(fn () => $this->redirect(
                    FeedImportResource::getUrl('edit', [
                        'record' => $this->record,
                    ]),
                )),
            Action::make('apply')
                ->label('Import')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Import reviewed records?')
                ->modalDescription('Listings and sizes will be updated in one operation.')
                ->modalSubmitActionLabel('Import')
                ->visible(fn (): bool => $this->record->status === FeedImport::STATUS_READY)
                ->disabled(fn (): bool => ! $this->record->canApply())
                ->tooltip(fn (): ?string => $this->record->canApply()
                    ? null
                    : 'Review or ignore every record that needs a decision.')
                ->action(function (): void {
                    try {
                        $this->record = app(FeedImportQueue::class)
                            ->apply($this->record);
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title('Import could not be completed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->status === FeedImport::STATUS_APPLIED
                        ? Notification::make()
                            ->title('Data imported')
                            ->success()
                            ->send()
                        : Notification::make()
                            ->title('Import queued')
                            ->info()
                            ->send();

                    $this->redirect(FeedImportResource::getUrl('index'));
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
