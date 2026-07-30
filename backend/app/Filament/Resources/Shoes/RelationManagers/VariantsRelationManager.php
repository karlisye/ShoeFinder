<?php

namespace App\Filament\Resources\Shoes\RelationManagers;

use App\Enums\ImageSourceType;
use App\Enums\ListingSourceType;
use App\Models\Colour;
use App\Models\Retailer;
use App\Models\RetailerListing;
use App\Models\ShoeVariant;
use App\Models\Size;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Varianti un piedāvājumi';

    protected static ?string $modelLabel = 'variantu';

    protected static ?string $pluralModelLabel = 'varianti';

    protected static bool $hasTitleCaseModelLabel = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Varianta sadaļas')
                    ->tabs([
                        Tab::make('Variants')
                            ->schema([
                                Section::make('Varianta dati')
                                    ->schema([
                                        Select::make('colour_id')
                                            ->label('Krāsa')
                                            ->helperText('Vairāku krāsu modelim izmanto vienu ražotāja krāsas nosaukumu, piemēram, “White/Black”.')
                                            ->relationship('colour', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('code')
                                                    ->label('Kods')
                                                    ->helperText('Mazie burti, cipari un defises. Pēc izveides kodu nemaini.')
                                                    ->required()
                                                    ->maxLength(64)
                                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                                    ->unique(Colour::class, 'code'),
                                                TextInput::make('name')
                                                    ->label('Krāsas nosaukums')
                                                    ->helperText('Saglabā ražotāja vai veikala izmantoto nosaukumu. To netulko.')
                                                    ->required()
                                                    ->maxLength(255),
                                            ])
                                            ->createOptionUsing(
                                                fn (array $data): int => Colour::query()
                                                    ->create([
                                                        ...$data,
                                                        'sort_order' => 0,
                                                        'active' => true,
                                                    ])
                                                    ->getKey(),
                                            )
                                            ->createOptionAction(
                                                fn (Action $action): Action => $action
                                                    ->label('Izveidot jaunu krāsu')
                                                    ->modalHeading('Jauna krāsa')
                                                    ->modalSubmitActionLabel('Izveidot'),
                                            )
                                            ->unique(
                                                table: ShoeVariant::class,
                                                column: 'colour_id',
                                                ignoreRecord: true,
                                                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                                    ->where('shoe_id', $this->getOwnerRecord()->getKey()),
                                            )
                                            ->validationMessages([
                                                'unique' => 'Šim apavu modelim variants ar šo krāsu jau pastāv.',
                                            ])
                                            ->required(),
                                        TextInput::make('manufacturer_variant_code')
                                            ->label('Ražotāja varianta kods')
                                            ->maxLength(100)
                                            ->unique(
                                                table: ShoeVariant::class,
                                                column: 'manufacturer_variant_code',
                                                ignoreRecord: true,
                                                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                                    ->where('shoe_id', $this->getOwnerRecord()->getKey()),
                                            )
                                            ->validationMessages([
                                                'unique' => 'Šim apavu modelim variants ar šo ražotāja kodu jau pastāv.',
                                            ]),
                                        Toggle::make('active')
                                            ->label('Aktīvs')
                                            ->default(true),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Attēli')
                            ->schema([
                                Repeater::make('images')
                                    ->label('Attēli')
                                    ->relationship()
                                    ->defaultItems(0)
                                    ->schema([
                                        Select::make('source_type')
                                            ->label('Avots')
                                            ->options([
                                                ImageSourceType::Local->value => 'Augšupielādēts fails',
                                                ImageSourceType::External->value => 'Ārēja adrese',
                                            ])
                                            ->default(ImageSourceType::Local->value)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                                if ($state === ImageSourceType::Local->value) {
                                                    $set('external_url', null);
                                                }

                                                if ($state === ImageSourceType::External->value) {
                                                    $set('path', null);
                                                }
                                            }),
                                        FileUpload::make('path')
                                            ->label('Fails')
                                            ->disk('public')
                                            ->directory('shoes')
                                            ->visibility('public')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096)
                                            ->required(fn (Get $get): bool => $get('source_type') === ImageSourceType::Local->value)
                                            ->visible(fn (Get $get): bool => $get('source_type') === ImageSourceType::Local->value),
                                        TextInput::make('external_url')
                                            ->label('Attēla adrese')
                                            ->url()
                                            ->required(fn (Get $get): bool => $get('source_type') === ImageSourceType::External->value)
                                            ->visible(fn (Get $get): bool => $get('source_type') === ImageSourceType::External->value),
                                        Textarea::make('alt_text_lv')
                                            ->label('Alternatīvais teksts latviski')
                                            ->rows(2),
                                        Textarea::make('alt_text_en')
                                            ->label('Alternatīvais teksts angliski')
                                            ->rows(2),
                                        TextInput::make('sort_order')
                                            ->label('Secība')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->maxValue(32767)
                                            ->default(0)
                                            ->required(),
                                        Toggle::make('is_primary')
                                            ->label('Galvenais attēls')
                                            ->default(false),
                                    ])
                                    ->columns(2)
                                    ->itemLabel(fn (array $state): string => filled($state['alt_text_lv'] ?? null)
                                        ? $state['alt_text_lv']
                                        : 'Attēls')
                                    ->addActionLabel('Pievienot attēlu')
                                    ->collapsible(),
                            ])
                            ->visible(fn (?ShoeVariant $record): bool => $record !== null),
                        Tab::make('Piedāvājumi')
                            ->schema([
                                Repeater::make('retailerListings')
                                    ->label('Veikalu piedāvājumi')
                                    ->relationship()
                                    ->defaultItems(0)
                                    ->schema([
                                        Section::make('Piedāvājums')
                                            ->schema([
                                                Select::make('retailer_id')
                                                    ->label('Veikals')
                                                    ->relationship('retailer', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                    ->required(),
                                                TextInput::make('product_url')
                                                    ->label('Produkta adrese')
                                                    ->url()
                                                    ->required(),
                                                TextInput::make('affiliate_url')
                                                    ->label('Partnerprogrammas saite')
                                                    ->helperText('Nav obligāta. Ja norādīta, pircējs tiks novirzīts uz šo saiti.')
                                                    ->url(),
                                                Select::make('source_type')
                                                    ->label('Datu avots')
                                                    ->options([
                                                        ListingSourceType::Manual->value => 'Manuāli',
                                                        ListingSourceType::Feed->value => 'Datu plūsma',
                                                        ListingSourceType::Api->value => 'API',
                                                    ])
                                                    ->default(ListingSourceType::Manual->value)
                                                    ->required(),
                                                Toggle::make('active')
                                                    ->label('Aktīvs')
                                                    ->default(true),
                                                DateTimePicker::make('last_checked_at')
                                                    ->label('Pēdējā pārbaude')
                                                    ->seconds(false)
                                                    ->default(now()),
                                            ])
                                            ->columns(2),
                                        Section::make('Cena un piegāde')
                                            ->schema([
                                                TextInput::make('current_price')
                                                    ->label('Cena')
                                                    ->helperText('Pašreizējā cena. Ja precei ir atlaide, norādi cenu pēc atlaides.')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(0.01)
                                                    ->required(),
                                                TextInput::make('original_price')
                                                    ->label('Cena pirms atlaides')
                                                    ->helperText('Atstāj tukšu, ja atlaides nav.')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(0.01)
                                                    ->gte('current_price'),
                                                TextInput::make('currency')
                                                    ->label('Valūta')
                                                    ->default('EUR')
                                                    ->length(3)
                                                    ->rules(['uppercase', 'regex:/^[A-Z]{3}$/'])
                                                    ->required(),
                                                TextInput::make('delivery_cost')
                                                    ->label('Piegādes cena')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(0.01),
                                                TextInput::make('delivery_min_days')
                                                    ->label('Piegāde no, dienas')
                                                    ->numeric()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->maxValue(32767),
                                                TextInput::make('delivery_max_days')
                                                    ->label('Piegāde līdz, dienas')
                                                    ->numeric()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->maxValue(32767)
                                                    ->gte('delivery_min_days'),
                                                Textarea::make('delivery_note_lv')
                                                    ->label('Piegādes piezīme latviski')
                                                    ->rows(2),
                                                Textarea::make('delivery_note_en')
                                                    ->label('Piegādes piezīme angliski')
                                                    ->rows(2),
                                            ])
                                            ->columns(2),
                                        Section::make('Veikala preces identifikatori')
                                            ->description('Nav obligāti manuālai ievadei. Tie būs vajadzīgi datu importam un vienas preces atpazīšanai dažādos avotos.')
                                            ->schema([
                                                TextInput::make('retailer_external_id')
                                                    ->label('Veikala ārējais ID')
                                                    ->maxLength(191),
                                                TextInput::make('retailer_sku')
                                                    ->label('Veikala SKU')
                                                    ->maxLength(191),
                                                TextInput::make('gtin')
                                                    ->label('GTIN vai EAN')
                                                    ->rules(['regex:/^(?:[0-9]{8}|[0-9]{12}|[0-9]{13}|[0-9]{14})$/']),
                                                TextInput::make('manufacturer_style_code')
                                                    ->label('Ražotāja modeļa kods')
                                                    ->maxLength(100),
                                                Textarea::make('raw_title')
                                                    ->label('Veikala sākotnējais nosaukums')
                                                    ->rows(2),
                                                TextInput::make('raw_colour')
                                                    ->label('Veikala sākotnējā krāsa')
                                                    ->maxLength(255),
                                                KeyValue::make('raw_payload')
                                                    ->label('Sākotnējie importa dati')
                                                    ->keyLabel('Lauks')
                                                    ->valueLabel('Vērtība')
                                                    ->addActionLabel('Pievienot lauku')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2)
                                            ->collapsed(),
                                        CheckboxList::make('quick_size_ids')
                                            ->label('Izmēru ātrā izvēle')
                                            ->helperText('Atzīmētie izmēri ir pieejami ar piedāvājuma cenu. Noņemot atzīmi, izmērs tiks noņemts no piedāvājuma.')
                                            ->options(fn (): array => Size::query()
                                                ->where('active', true)
                                                ->orderBy('sort_order')
                                                ->pluck('label', 'id')
                                                ->all())
                                            ->columns([
                                                'sm' => 4,
                                                'lg' => 8,
                                            ])
                                            ->bulkToggleable()
                                            ->live()
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function (
                                                CheckboxList $component,
                                                ?RetailerListing $record,
                                            ): void {
                                                $component->state(
                                                    $record?->listingSizes()
                                                        ->pluck('size_id')
                                                        ->map(fn (int $sizeId): string => (string) $sizeId)
                                                        ->all() ?? [],
                                                );
                                            })
                                            ->afterStateUpdated(function (?array $state, Get $get, Set $set): void {
                                                $selectedSizeIds = collect($state ?? [])
                                                    ->map(fn (mixed $sizeId): string => (string) $sizeId);
                                                $listingSizes = collect($get('listingSizes') ?? [])
                                                    ->filter(fn (array $listingSize): bool => $selectedSizeIds
                                                        ->contains((string) ($listingSize['size_id'] ?? '')))
                                                    ->values();
                                                $existingSizeIds = $listingSizes
                                                    ->pluck('size_id')
                                                    ->filter()
                                                    ->map(fn (mixed $sizeId): string => (string) $sizeId)
                                                    ->all();

                                                foreach ($state as $sizeId) {
                                                    if (in_array((string) $sizeId, $existingSizeIds, true)) {
                                                        continue;
                                                    }

                                                    $listingSizes->push([
                                                        'size_id' => $sizeId,
                                                        'in_stock' => true,
                                                        'price' => null,
                                                    ]);
                                                    $existingSizeIds[] = (string) $sizeId;
                                                }

                                                $set('listingSizes', $listingSizes->all());
                                            }),
                                        Repeater::make('listingSizes')
                                            ->label('Pieejamie izmēri')
                                            ->relationship()
                                            ->defaultItems(0)
                                            ->schema([
                                                Select::make('size_id')
                                                    ->label('EU izmērs')
                                                    ->relationship(
                                                        'size',
                                                        'label',
                                                        modifyQueryUsing: fn ($query) => $query->orderBy('sort_order'),
                                                    )
                                                    ->searchable()
                                                    ->preload()
                                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                    ->required(),
                                                Toggle::make('in_stock')
                                                    ->label('Pieejams')
                                                    ->default(true)
                                                    ->required(),
                                                TextInput::make('price')
                                                    ->label('Atsevišķa cena')
                                                    ->helperText('Atstāt tukšu, lai izmantotu piedāvājuma cenu.')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(0.01),
                                            ])
                                            ->columns(3)
                                            ->itemLabel(fn (array $state): string => filled($state['size_id'] ?? null)
                                                ? 'Izmēra ieraksts'
                                                : 'Jauns izmērs')
                                            ->addActionLabel('Pievienot izmēru')
                                            ->collapsible(),
                                    ])
                                    ->itemLabel(function (array $state): string {
                                        $retailerName = filled($state['retailer_id'] ?? null)
                                            ? Retailer::query()->whereKey($state['retailer_id'])->value('name')
                                            : null;

                                        return filled($retailerName)
                                            ? "Piedāvājums ({$retailerName})"
                                            : 'Veikala piedāvājums';
                                    })
                                    ->addActionLabel('Pievienot piedāvājumu')
                                    ->deleteAction(fn (Action $action): Action => $action
                                        ->label('Dzēst piedāvājumu')
                                        ->requiresConfirmation()
                                        ->modalHeading('Dzēst piedāvājumu?')
                                        ->modalDescription('Tiks dzēsti arī piedāvājuma izmēri, cenu vēsture un klikšķu dati.')
                                        ->modalSubmitActionLabel('Dzēst'))
                                    ->collapsible(),
                            ])
                            ->visible(fn (?ShoeVariant $record): bool => $record !== null),
                    ])
                    ->vertical()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('manufacturer_variant_code')
            ->columns([
                TextColumn::make('colour.name')
                    ->label('Krāsa')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('manufacturer_variant_code')
                    ->label('Varianta kods')
                    ->placeholder('Nav norādīts')
                    ->searchable(),
                TextColumn::make('images_count')
                    ->label('Attēli')
                    ->counts('images'),
                TextColumn::make('retailer_listings_count')
                    ->label('Piedāvājumi')
                    ->counts('retailerListings'),
                IconColumn::make('active')
                    ->label('Aktīvs')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Pievienot variantu')
                    ->modalHeading('Pievienot variantu')
                    ->slideOver()
                    ->modalWidth(Width::Full),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Rediģēt')
                    ->modalHeading('Rediģēt variantu')
                    ->slideOver()
                    ->modalWidth(Width::Full),
                DeleteAction::make()
                    ->label('Dzēst')
                    ->visible(fn (ShoeVariant $record): bool => ! $record->retailerListings()->exists()),
            ])
            ->defaultSort('id');
    }
}
