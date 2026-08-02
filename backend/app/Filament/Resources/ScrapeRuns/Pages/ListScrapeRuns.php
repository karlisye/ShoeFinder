<?php

namespace App\Filament\Resources\ScrapeRuns\Pages;

use App\Filament\Actions\StartScrapeRunAction;
use App\Filament\Resources\ScrapeRuns\ScrapeRunResource;
use Filament\Resources\Pages\ListRecords;

class ListScrapeRuns extends ListRecords
{
    protected static string $resource = ScrapeRunResource::class;

    protected function getHeaderActions(): array
    {
        return [StartScrapeRunAction::make()];
    }
}
