<?php

namespace App\Filament\Resources\Shoes\Pages;

use App\Filament\Resources\Shoes\ShoeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShoes extends ListRecords
{
    protected static string $resource = ShoeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
