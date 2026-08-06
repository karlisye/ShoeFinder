<?php

namespace App\Filament\Actions;

use App\Domain\Scraping\ScrapeRunQueue;
use App\Filament\Resources\ScrapeRuns\ScrapeRunResource;
use App\Models\Retailer;
use App\Models\ScrapeRun;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

class StartScrapeRunAction
{
    public static function make(): Action
    {
        return Action::make('startScrape')
            ->label('Scrape listing data')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->schema([
                Select::make('scope')
                    ->label('Listings to scrape')
                    ->options(fn (): array => self::scopeOptions())
                    ->default('all')
                    ->required(),
            ])
            ->modalHeading('Scrape listing data')
            ->modalDescription('Product pages will be checked in the background. Catalogue data will not change until the preview is approved.')
            ->modalSubmitActionLabel('Start scrape')
            ->disabled(fn (): bool => ScrapeRun::query()->whereIn('status', [
                ScrapeRun::STATUS_QUEUED,
                ScrapeRun::STATUS_SCRAPING,
                ScrapeRun::STATUS_APPLY_QUEUED,
                ScrapeRun::STATUS_APPLYING,
            ])->exists())
            ->tooltip(fn (): ?string => ScrapeRun::query()->whereIn('status', [
                ScrapeRun::STATUS_QUEUED,
                ScrapeRun::STATUS_SCRAPING,
                ScrapeRun::STATUS_APPLY_QUEUED,
                ScrapeRun::STATUS_APPLYING,
            ])->exists() ? 'Another scrape run is already in progress.' : null)
            ->action(function (array $data, Action $action): void {
                try {
                    $retailer = self::retailerFromScope($data['scope']);
                    $run = app(ScrapeRunQueue::class)->start($retailer, auth()->user());
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()
                        ->title('Scrape could not be started')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                    $action->failure();

                    return;
                }

                Notification::make()
                    ->title('Scrape queued')
                    ->body("{$run->total_count} listings will be checked.")
                    ->info()
                    ->send();
                $action->success();
                $action->redirect(ScrapeRunResource::getUrl('view', ['record' => $run]));
            });
    }

    private static function scopeOptions(): array
    {
        $queue = app(ScrapeRunQueue::class);
        $options = [
            'all' => 'All supported retailers ('.$queue->eligibleCount().')',
        ];

        foreach (Retailer::query()
            ->whereIn('slug', array_keys(config('scraper.retailers', [])))
            ->orderBy('name')
            ->get() as $retailer) {
            $options["retailer:{$retailer->id}"] = "{$retailer->name} ({$queue->eligibleCount($retailer)})";
        }

        return $options;
    }

    private static function retailerFromScope(string $scope): ?Retailer
    {
        if ($scope === 'all') {
            return null;
        }

        if (! preg_match('/^retailer:([0-9]+)$/', $scope, $matches)) {
            return null;
        }

        return Retailer::query()->findOrFail((int) $matches[1]);
    }
}
