<?php

namespace App\Filament\Resources\FeedImports\Pages;

use App\Domain\Feeds\FeedImportQueue;
use App\Filament\Resources\FeedImports\FeedImportResource;
use App\Models\FeedImport;
use App\Models\Retailer;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Throwable;

class CreateFeedImport extends CreateRecord
{
    protected static string $resource = FeedImportResource::class;

    public function getHeading(): string
    {
        return 'New feed import';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $retailer = Retailer::query()->findOrFail($data['retailer_id']);
        $format = config("feeds.retailers.{$retailer->slug}.format");

        abort_if($format === null, 422, 'No product feed is configured for this retailer.');

        $data['user_id'] = auth()->id();
        $data['format'] = $format;
        $data['status'] = FeedImport::STATUS_UPLOADED;
        $data['original_filename'] = $data['original_filename']
            ?? basename($data['stored_path']);

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            $this->record = app(FeedImportQueue::class)->preview($this->record);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Preview could not be queued')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        match ($this->record->status) {
            FeedImport::STATUS_READY => Notification::make()
                ->title('Preview created')
                ->success()
                ->send(),
            FeedImport::STATUS_FAILED => Notification::make()
                ->title('File could not be prepared')
                ->danger()
                ->send(),
            default => Notification::make()
                ->title('Preview queued')
                ->info()
                ->send(),
        };
    }

    protected function getRedirectUrl(): string
    {
        return FeedImportResource::getUrl('index');
    }
}
