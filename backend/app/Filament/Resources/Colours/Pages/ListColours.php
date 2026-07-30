<?php

namespace App\Filament\Resources\Colours\Pages;

use App\Filament\Resources\Colours\ColourResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListColours extends ListRecords
{
    protected static string $resource = ColourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
