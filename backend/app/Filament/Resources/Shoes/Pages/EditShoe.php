<?php

namespace App\Filament\Resources\Shoes\Pages;

use App\Filament\Resources\Shoes\ShoeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShoe extends EditRecord
{
    protected static string $resource = ShoeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->variants()
                    ->whereHas('retailerListings')
                    ->exists()),
        ];
    }
}
