<?php

namespace App\Filament\Resources\Colours\Pages;

use App\Filament\Resources\Colours\ColourResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditColour extends EditRecord
{
    protected static string $resource = ColourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->getRecord()
                    ->variants()
                    ->exists()),
        ];
    }
}
