<?php

namespace App\Filament\Resources\Shoes\RelationManagers;

use App\Enums\ImageSourceType;
use App\Enums\ListingSourceType;
use App\Models\Colour;
use App\Models\FilterColour;
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

    protected static ?string $title = 'Variants and listings';

    protected static ?string $modelLabel = 'variant';

    protected static ?string $pluralModelLabel = 'variants';

    protected static bool $hasTitleCaseModelLabel = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Variant sections')
                    ->tabs([
                        Tab::make('Variants')
                            ->schema([
                                Section::make('Variant details')
                                    ->schema([
                                        Select::make('colour_id')
                                            ->label('Colourway')
                                            ->helperText('Select the official colourway. Its filter colours are managed under Colourways.')
                                            ->relationship('colour', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('code')
                                                    ->label('Code')
                                                    ->helperText('Use lowercase letters, numbers, and hyphens. Do not change the code after creation.')
                                                    ->required()
                                                    ->maxLength(64)
                                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                                    ->unique(Colour::class, 'code'),
                                                TextInput::make('name')
                                                    ->label('Official name')
                                                    ->helperText('Use the manufacturer or retailer colourway name. Do not translate it.')
                                                    ->required()
                                                    ->maxLength(255),
                                                CheckboxList::make('filter_colour_ids')
                                                    ->label('Filter colours')
                                                    ->helperText('Select every colour visible in this colourway.')
                                                    ->options(fn (): array => FilterColour::query()
                                                        ->where('active', true)
                                                        ->orderBy('sort_order')
                                                        ->pluck('name_en', 'id')
                                                        ->all())
                                                    ->columns(3)
                                                    ->minItems(1)
                                                    ->required(),
                                            ])
                                            ->createOptionUsing(
                                                function (array $data): int {
                                                    $filterColourIds = $data['filter_colour_ids'];
                                                    unset($data['filter_colour_ids']);
                                                    $colour = Colour::query()->create([
                                                        ...$data,
                                                        'sort_order' => 0,
                                                        'active' => true,
                                                    ]);
                                                    $colour->filterColours()->sync($filterColourIds);

                                                    return $colour->getKey();
                                                },
                                            )
                                            ->createOptionAction(
                                                fn (Action $action): Action => $action
                                                    ->label('Create colourway')
                                                    ->modalHeading('New colourway')
                                                    ->modalSubmitActionLabel('Create'),
                                            )
                                            ->unique(
                                                table: ShoeVariant::class,
                                                column: 'colour_id',
                                                ignoreRecord: true,
                                                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                                    ->where('shoe_id', $this->getOwnerRecord()->getKey()),
                                            )
                                            ->validationMessages([
                                                'unique' => 'This shoe already has a variant with this colourway.',
                                            ])
                                            ->required(),
                                        TextInput::make('manufacturer_variant_code')
                                            ->label('Manufacturer variant code')
                                            ->maxLength(100)
                                            ->unique(
                                                table: ShoeVariant::class,
                                                column: 'manufacturer_variant_code',
                                                ignoreRecord: true,
                                                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                                    ->where('shoe_id', $this->getOwnerRecord()->getKey()),
                                            )
                                            ->validationMessages([
                                                'unique' => 'This shoe already has a variant with this manufacturer code.',
                                            ]),
                                        Toggle::make('active')
                                            ->label('Active')
                                            ->default(true),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Images')
                            ->schema([
                                Repeater::make('images')
                                    ->label('Images')
                                    ->relationship()
                                    ->defaultItems(0)
                                    ->schema([
                                        Select::make('source_type')
                                            ->label('Source')
                                            ->options([
                                                ImageSourceType::Local->value => 'Uploaded file',
                                                ImageSourceType::External->value => 'External URL',
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
                                            ->label('File')
                                            ->disk('public')
                                            ->directory('shoes')
                                            ->visibility('public')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096)
                                            ->required(fn (Get $get): bool => $get('source_type') === ImageSourceType::Local->value)
                                            ->visible(fn (Get $get): bool => $get('source_type') === ImageSourceType::Local->value),
                                        TextInput::make('external_url')
                                            ->label('Image URL')
                                            ->url()
                                            ->required(fn (Get $get): bool => $get('source_type') === ImageSourceType::External->value)
                                            ->visible(fn (Get $get): bool => $get('source_type') === ImageSourceType::External->value),
                                        Textarea::make('alt_text_lv')
                                            ->label('Latvian alt text')
                                            ->rows(2),
                                        Textarea::make('alt_text_en')
                                            ->label('English alt text')
                                            ->rows(2),
                                        TextInput::make('sort_order')
                                            ->label('Sort order')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->maxValue(32767)
                                            ->default(0)
                                            ->required(),
                                        Toggle::make('is_primary')
                                            ->label('Primary image')
                                            ->default(false),
                                    ])
                                    ->columns(2)
                                    ->itemLabel(fn (array $state): string => filled($state['alt_text_lv'] ?? null)
                                        ? $state['alt_text_lv']
                                        : 'Image')
                                    ->addActionLabel('Add image')
                                    ->collapsible(),
                            ])
                            ->visible(fn (?ShoeVariant $record): bool => $record !== null),
                        Tab::make('Listings')
                            ->schema([
                                Repeater::make('retailerListings')
                                    ->label('Retailer listings')
                                    ->relationship()
                                    ->defaultItems(0)
                                    ->schema([
                                        Section::make('Listing')
                                            ->schema([
                                                Select::make('retailer_id')
                                                    ->label('Retailer')
                                                    ->relationship('retailer', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                    ->required(),
                                                TextInput::make('product_url')
                                                    ->label('Product URL')
                                                    ->url()
                                                    ->required(),
                                                TextInput::make('affiliate_url')
                                                    ->label('Affiliate URL')
                                                    ->helperText('Optional. When present, outbound clicks use this URL.')
                                                    ->url(),
                                                Select::make('source_type')
                                                    ->label('Source type')
                                                    ->options([
                                                        ListingSourceType::Manual->value => 'Manual',
                                                        ListingSourceType::Feed->value => 'Feed',
                                                        ListingSourceType::Api->value => 'API',
                                                    ])
                                                    ->default(ListingSourceType::Manual->value)
                                                    ->required(),
                                                Toggle::make('active')
                                                    ->label('Active')
                                                    ->default(true),
                                                DateTimePicker::make('last_checked_at')
                                                    ->label('Last checked')
                                                    ->seconds(false)
                                                    ->default(now()),
                                            ])
                                            ->columns(2),
                                        Section::make('Price and delivery')
                                            ->schema([
                                                TextInput::make('current_price')
                                                    ->label('Current price')
                                                    ->helperText('Current item price. Enter the discounted price when the item is on sale.')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(0.01)
                                                    ->required(),
                                                TextInput::make('original_price')
                                                    ->label('Original price')
                                                    ->helperText('Leave empty when there is no discount.')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(0.01)
                                                    ->gte('current_price'),
                                                TextInput::make('currency')
                                                    ->label('Currency')
                                                    ->default('EUR')
                                                    ->length(3)
                                                    ->rules(['uppercase', 'regex:/^[A-Z]{3}$/'])
                                                    ->required(),
                                                TextInput::make('delivery_cost')
                                                    ->label('Delivery cost')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(0.01),
                                                TextInput::make('delivery_min_days')
                                                    ->label('Minimum delivery days')
                                                    ->numeric()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->maxValue(32767),
                                                TextInput::make('delivery_max_days')
                                                    ->label('Maximum delivery days')
                                                    ->numeric()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->maxValue(32767)
                                                    ->gte('delivery_min_days'),
                                                Textarea::make('delivery_note_lv')
                                                    ->label('Latvian delivery note')
                                                    ->rows(2),
                                                Textarea::make('delivery_note_en')
                                                    ->label('English delivery note')
                                                    ->rows(2),
                                            ])
                                            ->columns(2),
                                        Section::make('Retailer product identifiers')
                                            ->description('Optional for manual entry. Imports use these fields to identify the same product across sources.')
                                            ->schema([
                                                TextInput::make('retailer_external_id')
                                                    ->label('Retailer external ID')
                                                    ->maxLength(191),
                                                TextInput::make('retailer_sku')
                                                    ->label('Retailer SKU')
                                                    ->maxLength(191),
                                                TextInput::make('gtin')
                                                    ->label('GTIN or EAN')
                                                    ->rules(['regex:/^(?:[0-9]{8}|[0-9]{12}|[0-9]{13}|[0-9]{14})$/']),
                                                TextInput::make('manufacturer_style_code')
                                                    ->label('Manufacturer style code')
                                                    ->maxLength(100),
                                                Textarea::make('raw_title')
                                                    ->label('Raw retailer title')
                                                    ->rows(2),
                                                TextInput::make('raw_colour')
                                                    ->label('Raw retailer colour')
                                                    ->maxLength(255),
                                                KeyValue::make('raw_payload')
                                                    ->label('Raw import data')
                                                    ->keyLabel('Field')
                                                    ->valueLabel('Value')
                                                    ->addActionLabel('Add field')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2)
                                            ->collapsed(),
                                        CheckboxList::make('quick_size_ids')
                                            ->label('Quick size selection')
                                            ->helperText('Checked sizes are in stock at the listing price. Unchecking a size removes it from the listing.')
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
                                            ->label('Available sizes')
                                            ->relationship()
                                            ->defaultItems(0)
                                            ->schema([
                                                Select::make('size_id')
                                                    ->label('EU size')
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
                                                    ->label('In stock')
                                                    ->default(true)
                                                    ->required(),
                                                TextInput::make('price')
                                                    ->label('Size-specific price')
                                                    ->helperText('Leave empty to use the listing price.')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(0.01),
                                            ])
                                            ->columns(3)
                                            ->itemLabel(fn (array $state): string => filled($state['size_id'] ?? null)
                                                ? 'Size entry'
                                                : 'New size')
                                            ->addActionLabel('Add size')
                                            ->collapsible(),
                                    ])
                                    ->itemLabel(function (array $state): string {
                                        $retailerName = filled($state['retailer_id'] ?? null)
                                            ? Retailer::query()->whereKey($state['retailer_id'])->value('name')
                                            : null;

                                        return filled($retailerName)
                                            ? "Listing ({$retailerName})"
                                            : 'Retailer listing';
                                    })
                                    ->addActionLabel('Add listing')
                                    ->deleteAction(fn (Action $action): Action => $action
                                        ->label('Delete listing')
                                        ->requiresConfirmation()
                                        ->modalHeading('Delete listing?')
                                        ->modalDescription('Its sizes, price history, and outbound click data will also be deleted.')
                                        ->modalSubmitActionLabel('Delete'))
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
                    ->label('Colourway')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('manufacturer_variant_code')
                    ->label('Variant code')
                    ->placeholder('Not provided')
                    ->searchable(),
                TextColumn::make('images_count')
                    ->label('Images')
                    ->counts('images'),
                TextColumn::make('retailer_listings_count')
                    ->label('Listings')
                    ->counts('retailerListings'),
                IconColumn::make('active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add variant')
                    ->modalHeading('Add variant')
                    ->slideOver()
                    ->modalWidth(Width::Full),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit')
                    ->modalHeading('Edit variant')
                    ->slideOver()
                    ->modalWidth(Width::Full),
                DeleteAction::make()
                    ->label('Delete')
                    ->visible(fn (ShoeVariant $record): bool => ! $record->retailerListings()->exists()),
            ])
            ->defaultSort('id');
    }
}
