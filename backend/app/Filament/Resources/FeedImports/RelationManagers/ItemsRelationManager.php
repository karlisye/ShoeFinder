<?php

namespace App\Filament\Resources\FeedImports\RelationManagers;

use App\Domain\Feeds\FeedImportWorkflow;
use App\Enums\Audience;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\FeedImport;
use App\Models\FeedImportItem;
use App\Models\Shoe;
use App\Models\ShoeVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Throwable;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Importa ieraksti';

    protected static ?string $modelLabel = 'ieraksts';

    protected static ?string $pluralModelLabel = 'ieraksti';

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
                    ->label('Rinda')
                    ->placeholder('Nav'),
                TextColumn::make('identity')
                    ->label('Identifikators')
                    ->searchable(),
                TextColumn::make('normalized_payload.title')
                    ->label('Nosaukums')
                    ->limit(45)
                    ->placeholder('Nav'),
                TextColumn::make('normalized_payload.colour')
                    ->label('Krāsa')
                    ->placeholder('Nav'),
                TextColumn::make('outcome')
                    ->label('Rezultāts')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::outcomeLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        'created', 'updated', 'unavailable' => 'success',
                        'manual_review' => 'warning',
                        'invalid' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('reason')
                    ->label('Iemesls')
                    ->formatStateUsing(fn (string $state): string => self::reasonLabel($state))
                    ->wrap(),
                TextColumn::make('resolution')
                    ->label('Lēmums')
                    ->formatStateUsing(fn (?string $state): string => self::resolutionLabel($state))
                    ->placeholder('Nav'),
            ])
            ->filters([
                SelectFilter::make('outcome')
                    ->label('Rezultāts')
                    ->options([
                        'created' => 'Jauns piedāvājums',
                        'updated' => 'Izmaiņas',
                        'unchanged' => 'Bez izmaiņām',
                        'unavailable' => 'Nav pieejams',
                        'manual_review' => 'Jāpārbauda',
                        'invalid' => 'Nederīgs',
                        'missing' => 'Nav failā',
                    ]),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Pārbaudīt')
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
                    ])
                    ->schema([
                        Placeholder::make('source_data')
                            ->label('Avota dati')
                            ->content(fn (FeedImportItem $record): string => self::sourceSummary($record)),
                        Select::make('resolution')
                            ->label('Lēmums')
                            ->options(fn (FeedImportItem $record): array => $record->canAttachToVariant()
                                ? [
                                    FeedImportItem::RESOLUTION_ATTACH => 'Piesaistīt esošam variantam',
                                    FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT => 'Izveidot jaunu krāsas variantu',
                                    FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT => 'Izveidot jaunu apavu modeli',
                                    FeedImportItem::RESOLUTION_IGNORE => 'Ignorēt ierakstu',
                                ]
                                : [
                                    FeedImportItem::RESOLUTION_IGNORE => 'Ignorēt ierakstu',
                                ])
                            ->live()
                            ->required(),
                        Section::make('Jauns apavu modelis')
                            ->schema([
                                Select::make('new_shoe_brand_id')
                                    ->label('Zīmols')
                                    ->options(fn () => Brand::query()
                                        ->where('active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                Select::make('new_shoe_category_id')
                                    ->label('Kategorija')
                                    ->options(fn () => Category::query()
                                        ->where('active', true)
                                        ->orderBy('sort_order')
                                        ->pluck('name_lv', 'id'))
                                    ->searchable()
                                    ->required(),
                                TextInput::make('new_shoe_name')
                                    ->label('Apavu modeļa nosaukums')
                                    ->helperText('Izmanto oficiālo ražotāja nosaukumu.')
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
                                    ->label('Adrese')
                                    ->helperText('Nemainīga daļa publiskajā adresē.')
                                    ->required()
                                    ->maxLength(255)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->unique(
                                        table: Shoe::class,
                                        column: 'slug',
                                        ignoreRecord: false,
                                    ),
                                Select::make('new_shoe_audience')
                                    ->label('Auditorija')
                                    ->options([
                                        Audience::Men->value => 'Vīriešiem',
                                        Audience::Women->value => 'Sievietēm',
                                        Audience::Unisex->value => 'Unisex',
                                        Audience::Kids->value => 'Bērniem',
                                    ])
                                    ->required(),
                                TextInput::make('new_shoe_style_code')
                                    ->label('Ražotāja modeļa kods')
                                    ->maxLength(100),
                            ])
                            ->columns(2)
                            ->visible(fn (Get $get): bool => $get('resolution')
                                === FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT),
                        Select::make('selected_variant_id')
                            ->label(fn (Get $get): string => $get('resolution')
                                === FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT
                                    ? 'Esošs šī modeļa variants'
                                    : 'Apavu variants')
                            ->helperText(fn (Get $get): ?string => $get('resolution')
                                === FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT
                                    ? 'Izvēlētais variants nosaka apavu modeli. Piedāvājumam tiks izveidots atsevišķs krāsas variants.'
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
                                            $variant->manufacturer_variant_code ?? 'bez koda',
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
                            ->label('Esoša krāsa')
                            ->helperText('Izvēlies esošu krāsu vai atstāj tukšu, lai izveidotu jaunu.')
                            ->placeholder('Izveidot jaunu krāsu')
                            ->options(function (Get $get) {
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

                                return $query
                                    ->get()
                                    ->mapWithKeys(fn (Colour $colour): array => [
                                        $colour->getKey() => sprintf(
                                            '%s (%s)',
                                            $colour->name,
                                            $colour->code,
                                        ),
                                    ]);
                            })
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
                            ->label('Jaunas krāsas kods')
                            ->helperText('Mazie burti, cipari un defises. Pēc izveides kodu nemaini.')
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
                            ->label('Jaunas krāsas nosaukums')
                            ->helperText('Saglabā ražotāja vai veikala izmantoto nosaukumu. To netulko.')
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
                        TextInput::make('new_manufacturer_variant_code')
                            ->label('Ražotāja varianta kods')
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
                    ->modalHeading('Pārbaudīt importa ierakstu')
                    ->modalSubmitActionLabel('Saglabāt lēmumu')
                    ->action(function (FeedImportItem $record, array $data): void {
                        try {
                            app(FeedImportWorkflow::class)->resolve(
                                $record,
                                $data['resolution'],
                                isset($data['selected_variant_id'])
                                    ? (int) $data['selected_variant_id']
                                    : null,
                                auth()->id(),
                                [
                                    'selected_colour_id' => $data['selected_colour_id'] ?? null,
                                    'new_colour_code' => $data['new_colour_code'] ?? null,
                                    'new_colour_name' => $data['new_colour_name'] ?? null,
                                    'new_manufacturer_variant_code' => $data['new_manufacturer_variant_code'] ?? null,
                                    'new_shoe_brand_id' => $data['new_shoe_brand_id'] ?? null,
                                    'new_shoe_category_id' => $data['new_shoe_category_id'] ?? null,
                                    'new_shoe_name' => $data['new_shoe_name'] ?? null,
                                    'new_shoe_slug' => $data['new_shoe_slug'] ?? null,
                                    'new_shoe_style_code' => $data['new_shoe_style_code'] ?? null,
                                    'new_shoe_audience' => $data['new_shoe_audience'] ?? null,
                                ],
                            );
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Lēmumu neizdevās saglabāt')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Lēmums saglabāts')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('source_record');
    }

    private static function outcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            'created' => 'Jauns piedāvājums',
            'updated' => 'Izmaiņas',
            'unchanged' => 'Bez izmaiņām',
            'unavailable' => 'Nav pieejams',
            'manual_review' => 'Jāpārbauda',
            'invalid' => 'Nederīgs',
            'missing' => 'Nav failā',
            default => $outcome,
        };
    }

    private static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'strong_variant_identity' => 'Atrasts drošs variants',
            'retailer_identity' => 'Atrasts esošs piedāvājums',
            'no_strong_match' => 'Nav drošas atbilstības',
            'ambiguous_strong_match' => 'Atrasti vairāki varianti',
            'retailer_identity_conflict' => 'Veikala identifikatori nesakrīt',
            'strong_identity_conflict' => 'Produkta identifikatori nesakrīt',
            'variant_listing_identity_conflict' => 'Variantam jau ir cits veikala piedāvājums',
            'not_present_in_snapshot' => 'Piedāvājums nav šajā failā',
            default => $reason,
        };
    }

    private static function resolutionLabel(?string $resolution): string
    {
        return match ($resolution) {
            FeedImportItem::RESOLUTION_ATTACH => 'Piesaistīts variantam',
            FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT => 'Jauns krāsas variants',
            FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT => 'Jauns apavu modelis',
            FeedImportItem::RESOLUTION_IGNORE => 'Ignorēts',
            default => 'Nav',
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
            : 'nav norādīta';

        return collect([
            $data['title'] ?? null,
            filled($data['colour'] ?? null) ? "Krāsa: {$data['colour']}" : null,
            $identifiers !== [] ? 'Kodi: '.implode(', ', $identifiers) : null,
            "Cena: {$price}",
            $sizes !== '' ? "Izmēri: {$sizes}" : null,
        ])->filter()->implode("\n");
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
