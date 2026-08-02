<?php

namespace App\Filament\Resources\ScrapeRuns\Pages;

use App\Domain\Scraping\ScrapeRunQueue;
use App\Filament\Resources\ScrapeRuns\ScrapeRunResource;
use App\Models\ScrapeRun;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewScrapeRun extends ViewRecord
{
    protected static string $resource = ScrapeRunResource::class;

    public function getHeading(): string
    {
        return "Scrape run #{$this->record->getKey()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshStatus')
                ->label('Refresh status')
                ->visible(fn (): bool => in_array($this->record->status, [
                    ScrapeRun::STATUS_QUEUED,
                    ScrapeRun::STATUS_SCRAPING,
                    ScrapeRun::STATUS_APPLY_QUEUED,
                    ScrapeRun::STATUS_APPLYING,
                ], true))
                ->action(fn () => $this->redirect(ScrapeRunResource::getUrl('view', ['record' => $this->record]))),
            Action::make('apply')
                ->label('Apply preview')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Apply all successful results?')
                ->modalDescription('Successful listing changes and freshness times will be applied together. Failed items will remain unchanged.')
                ->modalSubmitActionLabel('Apply preview')
                ->visible(fn (): bool => $this->record->status === ScrapeRun::STATUS_READY)
                ->disabled(fn (): bool => ! $this->record->canApply())
                ->action(function (Action $action): void {
                    try {
                        $this->record = app(ScrapeRunQueue::class)->apply($this->record);
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title('Preview could not be queued')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                        $action->failure();

                        return;
                    }

                    Notification::make()
                        ->title('Approval queued')
                        ->info()
                        ->send();
                    $action->success();
                    $action->redirect(ScrapeRunResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
