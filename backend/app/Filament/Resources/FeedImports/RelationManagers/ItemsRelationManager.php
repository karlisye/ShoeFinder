<?php

namespace App\Filament\Resources\FeedImports\RelationManagers;

use App\Domain\Catalogue\Colours\FilterColourSuggester;
use App\Domain\Feeds\FeedImportChangePreview;
use App\Domain\Feeds\FeedImportWorkflow;
use App\Enums\Audience;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\FeedImport;
use App\Models\FeedImportItem;
use App\Models\FilterColour;
use App\Models\Shoe;
use App\Models\ShoeVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Import records';

    protected static ?string $modelLabel = 'record';

    protected static ?string $pluralModelLabel = 'records';

    protected static bool $hasTitleCaseModelLabel = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_record')
                    ->label('Row')
                    ->placeholder('None'),
                TextColumn::make('identity')
                    ->label('Identity')
                    ->searchable(),
                TextColumn::make('normalized_payload.title')
                    ->label('Title')
                    ->limit(45)
                    ->placeholder('None'),
                TextColumn::make('normalized_payload.colour')
                    ->label('Colour')
                    ->placeholder('None'),
                TextColumn::make('outcome')
                    ->label('Outcome')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::outcomeLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        'created', 'updated', 'unavailable' => 'success',
                        'manual_review' => 'warning',
                        'invalid' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->formatStateUsing(fn (string $state): string => self::reasonLabel($state))
                    ->wrap(),
                TextColumn::make('resolution')
                    ->label('Decision')
                    ->formatStateUsing(fn (?string $state): string => self::resolutionLabel($state))
                    ->placeholder('None'),
            ])
            ->filters([
                SelectFilter::make('outcome')
                    ->label('Outcome')
                    ->options([
                        'created' => 'New listing',
                        'updated' => 'Changes',
                        'unchanged' => 'No changes',
                        'unavailable' => 'Unavailable',
                        'manual_review' => 'Needs review',
                        'invalid' => 'Invalid',
                        'missing' => 'Missing from file',
                    ]),
            ])
            ->recordActions([
                Action::make('viewChanges')
                    ->label('View changes')
                    ->icon('heroicon-o-arrows-right-left')
                    ->schema([
                        Placeholder::make('change_preview')
                            ->hiddenLabel()
                            ->html()
                            ->content(
                                fn (FeedImportItem $record): HtmlString => self::changePreviewHtml(
                                    app(FeedImportChangePreview::class)->build(
                                        $record,
                                    ),
                                ),
                            ),
                    ])
                    ->modalHeading(fn (FeedImportItem $record): string => "Planned changes for {$record->identity}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth(Width::FiveExtraLarge),
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (FeedImportItem $record): bool => $record->outcome === 'manual_review'
                        && $record->feedImport->status === FeedImport::STATUS_READY)
                    ->fillForm(fn (FeedImportItem $record): array => [
                        'resolution' => $record->resolution,
                        'selected_variant_id' => $record->selected_variant_id,
                        'selected_colour_id' => $record->selected_colour_id,
                        'new_colour_code' => $record->new_colour_code
                            ?? self::suggestColourCode(
                                $record->normalized_payload['colour'] ?? '',
                            ),
                        'new_colour_name' => $record->new_colour_name
                            ?? ($record->normalized_payload['colour'] ?? null),
                        'new_filter_colour_ids' => $record->new_filter_colour_ids
                            ?? app(FilterColourSuggester::class)->idsFor(
                                $record->normalized_payload['colour'] ?? '',
                            ),
                        'new_manufacturer_variant_code' => $record->new_manufacturer_variant_code
                            ?? ($record->normalized_payload['manufacturer_variant_code'] ?? null),
                        'new_shoe_brand_id' => $record->new_shoe_brand_id
                            ?? self::suggestBrandId($record),
                        'new_shoe_category_id' => $record->new_shoe_category_id,
                        'new_shoe_name' => $record->new_shoe_name
                            ?? self::suggestShoeName($record),
                        'new_shoe_slug' => $record->new_shoe_slug
                            ?? Str::slug(self::suggestShoeName($record)),
                        'new_shoe_style_code' => $record->new_shoe_style_code
                            ?? ($record->normalized_payload['manufacturer_style_code'] ?? null),
                        'new_shoe_audience' => $record->new_shoe_audience,
                        'confirm_identity_update' => false,
                    ])
                    ->schema([
                        Placeholder::make('source_data')
                            ->label('Source data')
                            ->content(fn (FeedImportItem $record): string => self::sourceSummary($record)),
                        Select::make('resolution')
                            ->label('Decision')
                            ->options(fn (FeedImportItem $record): array => match (true) {
                                $record->canAttachToVariant() => [
                                    FeedImportItem::RESOLUTION_ATTACH => 'Attach to existing variant',
                                    FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT => 'Create a new colour variant',
                                    FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT => 'Create a new shoe',
                                    FeedImportItem::RESOLUTION_IGNORE => 'Ignore record',
                                ],
                                $record->canUpdateMatchedListing() => [
                                    FeedImportItem::RESOLUTION_UPDATE_MATCHED => 'Update matched listing',
                                    FeedImportItem::RESOLUTION_IGNORE => 'Ignore record',
                                ],
                                default => [
                                    FeedImportItem::RESOLUTION_IGNORE => 'Ignore record',
                                ],
                            })
                            ->live()
                            ->required(),
                        Section::make('Identity comparison')
                            ->description('Only confirm this change when both columns describe the same retailer product.')
                            ->schema([
                                Placeholder::make('stored_identity')
                                    ->label('Stored listing')
                                    ->html()
                                    ->content(fn (FeedImportItem $record): HtmlString|string => self::storedIdentitySummary($record)),
                                Placeholder::make('incoming_identity')
                                    ->label('Incoming feed record')
                                    ->html()
                                    ->content(fn (FeedImportItem $record): HtmlString => self::incomingIdentitySummary($record)),
                                Checkbox::make('confirm_identity_update')
                                    ->label('I confirm that the incoming identities belong to this matched listing.')
                                    ->accepted()
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->visible(fn (Get $get): bool => $get('resolution')
                                === FeedImportItem::RESOLUTION_UPDATE_MATCHED),
                        Section::make('New shoe')
                            ->schema([
                                Select::make('new_shoe_brand_id')
                                    ->label('Brand')
                                    ->options(fn () => Brand::query()
                                        ->where('active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                Select::make('new_shoe_category_id')
                                    ->label('Category')
                                    ->options(fn () => Category::query()
                                        ->where('active', true)
                                        ->orderBy('sort_order')
                                        ->pluck('name_en', 'id'))
                                    ->searchable()
                                    ->required(),
                                TextInput::make('new_shoe_name')
                                    ->label('Shoe name')
                                    ->helperText('Use the official manufacturer name.')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (?string $state, Set $set) => $set(
                                            'new_shoe_slug',
                                            Str::slug($state ?? ''),
                                        ),
                                    ),
                                TextInput::make('new_shoe_slug')
                                    ->label('Slug')
                                    ->helperText('Stable part of the public URL.')
                                    ->required()
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->unique(
                                        table: Shoe::class,
                                        column: 'slug',
                                        ignoreRecord: false,
                                    ),
                                Select::make('new_shoe_audience')
                                    ->label('Audience')
                                    ->options([
                                        Audience::Men->value => 'Men',
                                        Audience::Women->value => 'Women',
                                        Audience::Unisex->value => 'Unisex',
                                        Audience::Kids->value => 'Kids',
                                    ])
                                    ->required(),
                                TextInput::make('new_shoe_style_code')
                                    ->label('Manufacturer style code')
                                    ->maxLength(100),
                            ])
                            ->columns(2)
                            ->visible(fn (Get $get): bool => $get('resolution')
                                === FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT),
                        Select::make('selected_variant_id')
                            ->label(fn (Get $get): string => $get('resolution')
                                === FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT
                                    ? 'Existing variant from this shoe'
                                    : 'Shoe variant')
                            ->helperText(fn (Get $get): ?string => $get('resolution')
                                === FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT
                                    ? 'The selected variant identifies the shoe. A separate colour variant will be created for this listing.'
                                    : null)
                            ->options(function (FeedImportItem $record, Get $get) {
                                $query = ShoeVariant::query()
                                    ->with(['shoe.brand', 'colour'])
                                    ->where('active', true);

                                if ($get('resolution') === FeedImportItem::RESOLUTION_ATTACH) {
                                    $query->whereDoesntHave(
                                        'retailerListings',
                                        fn ($listingQuery) => $listingQuery->where(
                                            'retailer_id',
                                            $record->feedImport->retailer_id,
                                        ),
                                    );
                                }

                                return $query
                                    ->get()
                                    ->mapWithKeys(fn (ShoeVariant $variant): array => [
                                        $variant->getKey() => sprintf(
                                            '%s %s, %s (%s)',
                                            $variant->shoe->brand->name,
                                            $variant->shoe->name,
                                            $variant->colour->name,
                                            $variant->manufacturer_variant_code ?? 'no code',
                                        ),
                                    ]);
                            })
                            ->searchable()
                            ->live()
                            ->required(fn (Get $get): bool => in_array(
                                $get('resolution'),
                                [
                                    FeedImportItem::RESOLUTION_ATTACH,
                                    FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                                ],
                                true,
                            ))
                            ->visible(fn (Get $get): bool => in_array(
                                $get('resolution'),
                                [
                                    FeedImportItem::RESOLUTION_ATTACH,
                                    FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                                ],
                                true,
                            )),
                        Select::make('selected_colour_id')
                            ->label('Existing colourway')
                            ->helperText('Select a saved or pending colourway. Leave empty to create one.')
                            ->placeholder('Create a new colourway')
                            ->options(
                                fn (FeedImportItem $record, Get $get): array => self::colourOptions(
                                    $record,
                                    $get,
                                ),
                            )
                            ->searchable()
                            ->live()
                            ->visible(fn (Get $get): bool => in_array(
                                $get('resolution'),
                                [
                                    FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                                    FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
                                ],
                                true,
                            )),
                        TextInput::make('new_colour_code')
                            ->label('New colourway code')
                            ->helperText('Use lowercase letters, numbers, and hyphens. Do not change the code after creation.')
                            ->required(fn (Get $get): bool => blank($get('selected_colour_id')))
                            ->maxLength(64)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(
                                table: Colour::class,
                                column: 'code',
                                ignoreRecord: false,
                            )
                            ->visible(fn (Get $get): bool => blank($get('selected_colour_id'))
                                && in_array(
                                    $get('resolution'),
                                    [
                                        FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                                        FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
                                    ],
                                    true,
                                )),
                        TextInput::make('new_colour_name')
                            ->label('New colourway name')
                            ->helperText('Use the manufacturer or retailer colourway name. Do not translate it.')
                            ->required(fn (Get $get): bool => blank($get('selected_colour_id')))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => blank($get('selected_colour_id'))
                                && in_array(
                                    $get('resolution'),
                                    [
                                        FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                                        FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
                                    ],
                                    true,
                                )),
                        CheckboxList::make('new_filter_colour_ids')
                            ->label('Filter colours')
                            ->helperText('Select every visible colour. These values only affect catalogue filters.')
                            ->options(fn (): array => FilterColour::query()
                                ->where('active', true)
                                ->orderBy('sort_order')
                                ->pluck('name_en', 'id')
                                ->all())
                            ->columns(3)
                            ->minItems(1)
                            ->required(fn (Get $get): bool => blank($get('selected_colour_id')))
                            ->visible(fn (Get $get): bool => blank($get('selected_colour_id'))
                                && in_array(
                                    $get('resolution'),
                                    [
                                        FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                                        FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
                                    ],
                                    true,
                                )),
                        TextInput::make('new_manufacturer_variant_code')
                            ->label('Manufacturer variant code')
                            ->maxLength(100)
                            ->visible(fn (Get $get): bool => in_array(
                                $get('resolution'),
                                [
                                    FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                                    FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
                                ],
                                true,
                            )),
                    ])
                    ->modalHeading('Review import record')
                    ->modalSubmitActionLabel('Save decision')
                    ->action(function (FeedImportItem $record, array $data): void {
                        try {
                            $colourAttributes = self::colourAttributes(
                                $record,
                                $data,
                            );

                            app(FeedImportWorkflow::class)->resolve(
                                $record,
                                $data['resolution'],
                                isset($data['selected_variant_id'])
                                    ? (int) $data['selected_variant_id']
                                    : null,
                                auth()->id(),
                                [
                                    ...$colourAttributes,
                                    'new_manufacturer_variant_code' => $data['new_manufacturer_variant_code'] ?? null,
                                    'new_shoe_brand_id' => $data['new_shoe_brand_id'] ?? null,
                                    'new_shoe_category_id' => $data['new_shoe_category_id'] ?? null,
                                    'new_shoe_name' => $data['new_shoe_name'] ?? null,
                                    'new_shoe_slug' => $data['new_shoe_slug'] ?? null,
                                    'new_shoe_style_code' => $data['new_shoe_style_code'] ?? null,
                                    'new_shoe_audience' => $data['new_shoe_audience'] ?? null,
                                    'confirm_identity_update' => $data['confirm_identity_update'] ?? false,
                                ],
                            );
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Decision could not be saved')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Decision saved')
                            ->success()
                            ->send();

                        $this->dispatch('refresh-page');
                    }),
            ])
            ->defaultSort('source_record');
    }

    private static function colourOptions(
        FeedImportItem $record,
        Get $get,
    ): array {
        $query = Colour::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if (
            $get('resolution')
            === FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT
            && filled($get('selected_variant_id'))
        ) {
            $shoeId = ShoeVariant::query()
                ->whereKey($get('selected_variant_id'))
                ->value('shoe_id');

            if ($shoeId !== null) {
                $query->whereDoesntHave(
                    'variants',
                    fn ($variantQuery) => $variantQuery->where(
                        'shoe_id',
                        $shoeId,
                    ),
                );
            }
        }

        $storedColours = $query
            ->get()
            ->mapWithKeys(fn (Colour $colour): array => [
                $colour->getKey() => sprintf(
                    '%s (%s)',
                    $colour->name,
                    $colour->code,
                ),
            ]);
        $pendingColours = $record->feedImport->items()
            ->whereKeyNot($record->getKey())
            ->whereIn('resolution', [
                FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
            ])
            ->whereNotNull('new_colour_code')
            ->whereNotNull('new_colour_name')
            ->orderBy('id')
            ->get()
            ->unique('new_colour_code')
            ->mapWithKeys(fn (FeedImportItem $item): array => [
                "pending:{$item->getKey()}" => sprintf(
                    '%s (%s, pending import)',
                    $item->new_colour_name,
                    $item->new_colour_code,
                ),
            ]);

        return $storedColours->union($pendingColours)->all();
    }

    private static function colourAttributes(
        FeedImportItem $record,
        array $data,
    ): array {
        $selection = $data['selected_colour_id'] ?? null;

        if (blank($selection)) {
            return [
                'selected_colour_id' => null,
                'new_colour_code' => $data['new_colour_code'] ?? null,
                'new_colour_name' => $data['new_colour_name'] ?? null,
                'new_filter_colour_ids' => $data['new_filter_colour_ids'] ?? [],
            ];
        }

        if (! str_starts_with((string) $selection, 'pending:')) {
            if (! ctype_digit((string) $selection)) {
                throw new LogicException('Select a valid colourway.');
            }

            return [
                'selected_colour_id' => (int) $selection,
                'new_colour_code' => null,
                'new_colour_name' => null,
                'new_filter_colour_ids' => null,
            ];
        }

        $pendingId = str((string) $selection)
            ->after('pending:')
            ->toString();

        if (! ctype_digit($pendingId)) {
            throw new LogicException('Select a valid pending colourway.');
        }

        $pendingColour = $record->feedImport->items()
            ->whereKeyNot($record->getKey())
            ->whereKey((int) $pendingId)
            ->whereIn('resolution', [
                FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
            ])
            ->whereNotNull('new_colour_code')
            ->whereNotNull('new_colour_name')
            ->first();

        if ($pendingColour === null) {
            throw new LogicException('The pending colourway is no longer available.');
        }

        return [
            'selected_colour_id' => null,
            'new_colour_code' => $pendingColour->new_colour_code,
            'new_colour_name' => $pendingColour->new_colour_name,
            'new_filter_colour_ids' => $pendingColour->new_filter_colour_ids,
        ];
    }

    private static function outcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            'created' => 'New listing',
            'updated' => 'Changes',
            'unchanged' => 'No changes',
            'unavailable' => 'Unavailable',
            'manual_review' => 'Needs review',
            'invalid' => 'Invalid',
            'missing' => 'Missing from file',
            default => $outcome,
        };
    }

    private static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'strong_variant_identity' => 'Strong variant match',
            'retailer_identity' => 'Existing listing found',
            'no_strong_match' => 'No strong match',
            'ambiguous_strong_match' => 'Multiple variants found',
            'retailer_identity_conflict' => 'Retailer identities conflict',
            'strong_identity_conflict' => 'Product identities conflict',
            'variant_listing_identity_conflict' => 'Variant already has another listing from this retailer',
            'not_present_in_snapshot' => 'Listing is missing from this file',
            default => $reason,
        };
    }

    private static function resolutionLabel(?string $resolution): string
    {
        return match ($resolution) {
            FeedImportItem::RESOLUTION_ATTACH => 'Attached to variant',
            FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT => 'New colour variant',
            FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT => 'New shoe',
            FeedImportItem::RESOLUTION_UPDATE_MATCHED => 'Matched listing update',
            FeedImportItem::RESOLUTION_IGNORE => 'Ignored',
            default => 'None',
        };
    }

    private static function sourceSummary(FeedImportItem $item): string
    {
        $data = $item->normalized_payload ?? [];
        $identifiers = array_filter([
            $data['retailer_external_id'] ?? null,
            $data['retailer_sku'] ?? null,
            $data['gtin'] ?? null,
            $data['manufacturer_variant_code'] ?? null,
            $data['manufacturer_style_code'] ?? null,
        ]);
        $sizes = collect($data['sizes'] ?? [])
            ->pluck('eu_size')
            ->implode(', ');
        $price = isset($data['current_price'])
            ? "{$data['current_price']} ".($data['currency'] ?? '')
            : 'not provided';

        return collect([
            $data['title'] ?? null,
            filled($data['colour'] ?? null) ? "Colour: {$data['colour']}" : null,
            $identifiers !== [] ? 'Codes: '.implode(', ', $identifiers) : null,
            "Price: {$price}",
            $sizes !== '' ? "Sizes: {$sizes}" : null,
        ])->filter()->implode("\n");
    }

    private static function changePreviewHtml(array $preview): HtmlString
    {
        $content = sprintf(
            '<p style="margin: 0; font-size: 0.875rem;">%s</p>',
            e($preview['summary']),
        );

        if (! $preview['will_apply']) {
            return new HtmlString($content);
        }

        $content .= self::changeTable(
            'Listing fields',
            'Field',
            $preview['fields'],
        );
        $content .= self::changeTable(
            'Size availability',
            'Size',
            $preview['sizes'],
        );

        if ($preview['fields'] === [] && $preview['sizes'] === []) {
            $content .= '<p style="margin: 1rem 0 0; font-size: 0.875rem; opacity: 0.75;">No visible listing or size values will change. The stored source snapshot will still be refreshed.</p>';
        }

        return new HtmlString($content);
    }

    private static function changeTable(
        string $heading,
        string $firstColumn,
        array $rows,
    ): string {
        if ($rows === []) {
            return '';
        }

        $body = collect($rows)
            ->map(fn (array $row): string => sprintf(
                '<tr><th scope="row" style="width: 28%%; padding: 0.65rem 0.75rem; border-top: 1px solid rgba(128, 128, 128, 0.25); text-align: left; vertical-align: top; font-weight: 600; overflow-wrap: anywhere;">%s</th><td style="width: 36%%; padding: 0.65rem 0.75rem; border-top: 1px solid rgba(128, 128, 128, 0.25); vertical-align: top; overflow-wrap: anywhere;">%s</td><td style="width: 36%%; padding: 0.65rem 0.75rem; border-top: 1px solid rgba(128, 128, 128, 0.25); vertical-align: top; overflow-wrap: anywhere;">%s</td></tr>',
                e($row['label']),
                e($row['current']),
                e($row['incoming']),
            ))
            ->implode('');

        return sprintf(
            '<section style="margin-top: 1.5rem;"><h3 style="margin: 0; font-size: 1rem; font-weight: 600;">%s</h3><div style="margin-top: 0.75rem; overflow-x: auto; border: 1px solid rgba(128, 128, 128, 0.3); border-radius: 0.5rem;"><table style="width: 100%%; min-width: 36rem; border-collapse: collapse; table-layout: fixed; font-size: 0.875rem;"><thead style="background: rgba(128, 128, 128, 0.1);"><tr><th style="width: 28%%; padding: 0.55rem 0.75rem; text-align: left; font-weight: 600;">%s</th><th style="width: 36%%; padding: 0.55rem 0.75rem; text-align: left; font-weight: 600;">Current</th><th style="width: 36%%; padding: 0.55rem 0.75rem; text-align: left; font-weight: 600;">Incoming</th></tr></thead><tbody>%s</tbody></table></div></section>',
            e($heading),
            e($firstColumn),
            $body,
        );
    }

    private static function storedIdentitySummary(FeedImportItem $item): HtmlString|string
    {
        $listing = $item->matchedListing?->loadMissing([
            'variant.shoe.brand',
            'variant.colour',
        ]);

        if ($listing === null) {
            return 'No matched listing';
        }

        return self::identitySummary([
            'Product' => sprintf(
                '%s %s, %s',
                $listing->variant->shoe->brand->name,
                $listing->variant->shoe->name,
                $listing->variant->colour->name,
            ),
            'Retailer external ID' => $listing->retailer_external_id,
            'Retailer SKU' => $listing->retailer_sku,
            'GTIN / EAN' => $listing->gtin,
            'Manufacturer style code' => $listing->manufacturer_style_code,
            'Manufacturer variant code' => $listing->variant->manufacturer_variant_code,
        ]);
    }

    private static function incomingIdentitySummary(FeedImportItem $item): HtmlString
    {
        $data = $item->normalized_payload ?? [];

        return self::identitySummary([
            'Product' => $data['title'] ?? null,
            'Retailer external ID' => $data['retailer_external_id'] ?? null,
            'Retailer SKU' => $data['retailer_sku'] ?? null,
            'GTIN / EAN' => $data['gtin'] ?? null,
            'Manufacturer style code' => $data['manufacturer_style_code'] ?? null,
            'Manufacturer variant code' => $data['manufacturer_variant_code'] ?? null,
        ]);
    }

    private static function identitySummary(array $values): HtmlString
    {
        $rows = collect($values)
            ->map(function (mixed $value, string $label): string {
                $displayValue = filled($value) ? $value : 'Not provided';

                return sprintf(
                    '<div><div class="text-xs font-medium text-gray-500 dark:text-gray-400">%s</div><div class="text-gray-950 dark:text-white">%s</div></div>',
                    e($label),
                    e($displayValue),
                );
            })
            ->implode('');

        return new HtmlString('<div class="space-y-2 text-sm">'.$rows.'</div>');
    }

    private static function suggestColourCode(string $colour): string
    {
        return (string) Str::of($colour)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-');
    }

    private static function suggestBrandId(FeedImportItem $item): ?int
    {
        $brand = $item->normalized_payload['brand'] ?? null;

        if (blank($brand)) {
            return null;
        }

        return Brand::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($brand)])
            ->value('id');
    }

    private static function suggestShoeName(FeedImportItem $item): string
    {
        $title = trim((string) ($item->normalized_payload['title'] ?? ''));
        $brand = trim((string) ($item->normalized_payload['brand'] ?? ''));

        if ($brand !== '' && str_starts_with(mb_strtolower($title), mb_strtolower($brand).' ')) {
            return trim(mb_substr($title, mb_strlen($brand)));
        }

        return $title;
    }
}
