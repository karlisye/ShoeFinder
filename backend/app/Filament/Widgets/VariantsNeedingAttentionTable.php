<?php

namespace App\Filament\Widgets;

use App\Domain\Analytics\CatalogueHealthMetrics;
use App\Filament\Resources\Shoes\ShoeResource;
use App\Models\ShoeVariant;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class VariantsNeedingAttentionTable extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                app(CatalogueHealthMetrics::class)
                    ->variantsNeedingAttentionQuery(),
            )
            ->heading('Variants needing attention')
            ->description('Open the parent shoe to fix images, offers, or sizes.')
            ->columns([
                TextColumn::make('shoe.name')
                    ->label('Shoe')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shoe.brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('colour.name')
                    ->label('Colourway')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('manufacturer_variant_code')
                    ->label('Variant code')
                    ->placeholder('Not provided')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('health_issues')
                    ->label('Issues')
                    ->state(fn (ShoeVariant $record): array => $this->issuesFor($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'No available offer' => 'danger',
                        default => 'warning',
                    })
                    ->wrap(),
                TextColumn::make('qualifying_listings_count')
                    ->label('Live offers')
                    ->numeric()
                    ->sortable(),
            ])
            ->recordUrl(
                fn (ShoeVariant $record): string => ShoeResource::getUrl(
                    'edit',
                    ['record' => $record->shoe_id],
                ),
            )
            ->recordActions([
                Action::make('openShoe')
                    ->label('Open shoe')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(
                        fn (ShoeVariant $record): string => ShoeResource::getUrl(
                            'edit',
                            ['record' => $record->shoe_id],
                        ),
                    ),
            ])
            ->emptyStateHeading('No variants need attention')
            ->emptyStateDescription('Every active public variant has a main image and a fresh in-stock offer.')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50])
            ->poll('60s');
    }

    /**
     * @return array<int, string>
     */
    private function issuesFor(ShoeVariant $variant): array
    {
        $issues = [];

        if (! (bool) $variant->has_primary_image) {
            $issues[] = 'Missing main image';
        }

        if ((int) $variant->qualifying_listings_count === 0) {
            $issues[] = 'No available offer';
        }

        if ((int) $variant->stale_listings_count > 0) {
            $issues[] = 'Stale offer';
        }

        if ((int) $variant->fresh_listings_without_stock_count > 0) {
            $issues[] = 'Offer without stock';
        }

        return $issues;
    }
}
