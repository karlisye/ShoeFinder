<?php

namespace App\Filament\Resources\Retailers\Pages;

use App\Filament\Resources\Retailers\RetailerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRetailers extends ListRecords
{
    protected static string $resource = RetailerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
