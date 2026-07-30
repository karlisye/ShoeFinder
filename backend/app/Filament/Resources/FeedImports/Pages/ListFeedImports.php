<?php

namespace App\Filament\Resources\FeedImports\Pages;

use App\Filament\Resources\FeedImports\FeedImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeedImports extends ListRecords
{
    protected static string $resource = FeedImportResource::class;

    public function getHeading(): string
    {
        return 'Feed imports';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New import'),
        ];
    }
}
