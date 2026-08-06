<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\StartScrapeRunAction;
use App\Filament\Resources\ScrapeRuns\ScrapeRunResource;
use App\Models\ScrapeRun;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ScrapeRunsWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -8;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(ScrapeRun::query()->latest())
            ->heading('Product-page scraper')
            ->description('Check supported retailer pages and review changes before applying them.')
            ->headerActions([StartScrapeRunAction::make()])
            ->columns([
                TextColumn::make('id')->label('Run')->prefix('#'),
                TextColumn::make('retailer.name')->label('Scope')->placeholder('All supported retailers'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ScrapeRunResource::statusLabel($state))
                    ->color(fn (?string $state): string => ScrapeRunResource::statusColor($state)),
                TextColumn::make('changed_count')->label('Changes')->numeric(),
                TextColumn::make('failed_count')->label('Failed')->numeric(),
                TextColumn::make('created_at')->label('Created')->dateTime('Y-m-d H:i'),
            ])
            ->recordUrl(fn (ScrapeRun $record): string => ScrapeRunResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No scrape runs yet')
            ->emptyStateDescription('Start a run to check supported manual listing URLs.')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->poll('3s');
    }
}
