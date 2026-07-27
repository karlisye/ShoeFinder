<?php

namespace App\Filament\Resources\Retailers\Pages;

use App\Filament\Resources\Retailers\RetailerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRetailer extends EditRecord
{
    protected static string $resource = RetailerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
