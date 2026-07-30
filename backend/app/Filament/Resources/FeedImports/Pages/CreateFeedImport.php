<?php

namespace App\Filament\Resources\FeedImports\Pages;

use App\Domain\Feeds\FeedImportWorkflow;
use App\Filament\Resources\FeedImports\FeedImportResource;
use App\Models\FeedImport;
use App\Models\Retailer;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateFeedImport extends CreateRecord
{
    protected static string $resource = FeedImportResource::class;

    public function getHeading(): string
    {
        return 'Jauns datu imports';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $retailer = Retailer::query()->findOrFail($data['retailer_id']);
        $format = config("feeds.retailers.{$retailer->slug}.format");

        abort_if($format === null, 422, 'Šim veikalam datu plūsma nav konfigurēta.');

        $data['user_id'] = auth()->id();
        $data['format'] = $format;
        $data['status'] = FeedImport::STATUS_UPLOADED;
        $data['original_filename'] = $data['original_filename']
            ?? basename($data['stored_path']);

        return $data;
    }

    protected function afterCreate(): void
    {
        app(FeedImportWorkflow::class)->preview($this->record);

        Notification::make()
            ->title($this->record->status === FeedImport::STATUS_READY
                ? 'Priekšskatījums izveidots'
                : 'Failu neizdevās sagatavot')
            ->status($this->record->status === FeedImport::STATUS_READY
                ? 'success'
                : 'danger')
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return FeedImportResource::getUrl('edit', ['record' => $this->record]);
    }
}
