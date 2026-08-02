<?php

namespace App\Filament\Resources\ScrapeRuns\RelationManagers;

use App\Models\ScrapeRunItem;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Listing results';

    protected static ?string $modelLabel = 'listing result';

    protected static ?string $pluralModelLabel = 'listing results';

    protected static bool $hasTitleCaseModelLabel = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position')->label('#')->numeric(),
                TextColumn::make('listing_label')->label('Listing')->searchable()->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        ScrapeRunItem::STATUS_CHANGED, ScrapeRunItem::STATUS_UNAVAILABLE => 'warning',
                        ScrapeRunItem::STATUS_UNCHANGED, ScrapeRunItem::STATUS_APPLIED => 'success',
                        ScrapeRunItem::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('baseline.current_price')
                    ->label('Stored price')
                    ->money('EUR')
                    ->placeholder('None'),
                TextColumn::make('result_payload.current_price')
                    ->label('Scraped price')
                    ->money('EUR')
                    ->placeholder('Unchanged'),
                TextColumn::make('error.message')
                    ->label('Error')
                    ->placeholder('None')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    ScrapeRunItem::STATUS_PENDING => 'Pending',
                    ScrapeRunItem::STATUS_CHANGED => 'Changes',
                    ScrapeRunItem::STATUS_UNCHANGED => 'No changes',
                    ScrapeRunItem::STATUS_UNAVAILABLE => 'Unavailable',
                    ScrapeRunItem::STATUS_FAILED => 'Failed',
                    ScrapeRunItem::STATUS_APPLIED => 'Applied',
                ]),
            ])
            ->recordActions([
                Action::make('viewChanges')
                    ->label('View result')
                    ->icon('heroicon-o-arrows-right-left')
                    ->schema([
                        Placeholder::make('preview')
                            ->hiddenLabel()
                            ->html()
                            ->content(fn (ScrapeRunItem $record): HtmlString => self::previewHtml($record)),
                    ])
                    ->modalHeading(fn (ScrapeRunItem $record): string => $record->listing_label)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth(Width::FiveExtraLarge),
            ])
            ->poll('3s')
            ->paginated([10, 25, 50]);
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            ScrapeRunItem::STATUS_PENDING => 'Pending',
            ScrapeRunItem::STATUS_CHANGED => 'Changes found',
            ScrapeRunItem::STATUS_UNCHANGED => 'No changes',
            ScrapeRunItem::STATUS_UNAVAILABLE => 'Unavailable',
            ScrapeRunItem::STATUS_FAILED => 'Failed',
            ScrapeRunItem::STATUS_APPLIED => 'Applied',
            default => 'Unknown',
        };
    }

    private static function previewHtml(ScrapeRunItem $item): HtmlString
    {
        if ($item->status === ScrapeRunItem::STATUS_FAILED) {
            return new HtmlString(
                '<p><strong>Scrape failed:</strong> '.e($item->error['message'] ?? 'Unknown error').'</p>'
                .'<p>The catalogue listing will remain unchanged.</p>',
            );
        }

        $html = '<dl class="grid gap-3">';
        foreach ($item->changes['listing'] ?? [] as $field => $change) {
            $label = ucfirst(str_replace('_', ' ', $field));
            $html .= '<div><dt class="font-medium">'.e($label).'</dt><dd>'
                .e(self::displayValue($change['before'] ?? null)).' → '
                .e(self::displayValue($change['after'] ?? null)).'</dd></div>';
        }

        if (isset($item->changes['sizes'])) {
            $html .= '<div><dt class="font-medium">Available sizes</dt><dd>'
                .e(self::sizeSummary($item->changes['sizes']['before'] ?? [])).' → '
                .e(self::sizeSummary($item->changes['sizes']['after'] ?? [])).'</dd></div>';
        }

        if ($item->changes === null) {
            $html .= '<div><dt class="font-medium">Catalogue changes</dt><dd>None. Approval will only refresh the last checked time.</dd></div>';
        }

        $html .= '</dl>';

        return new HtmlString($html);
    }

    private static function displayValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'None',
            $value === true => 'Yes',
            $value === false => 'No',
            default => (string) $value,
        };
    }

    private static function sizeSummary(array $sizes): string
    {
        $available = collect($sizes)
            ->filter(fn (array $size): bool => (bool) ($size['in_stock'] ?? false))
            ->pluck('eu_size')
            ->implode(', ');

        return $available !== '' ? $available : 'No sizes in stock';
    }
}
