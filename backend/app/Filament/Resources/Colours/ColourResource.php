<?php

namespace App\Filament\Resources\Colours;

use App\Filament\Resources\Colours\Pages\CreateColour;
use App\Filament\Resources\Colours\Pages\EditColour;
use App\Filament\Resources\Colours\Pages\ListColours;
use App\Models\Colour;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ColourResource extends Resource
{
    protected static ?string $model = Colour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Krāsu varianti';

    protected static ?string $modelLabel = 'krāsu variants';

    protected static ?string $pluralModelLabel = 'krāsu varianti';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Krāsu varianta dati')
                    ->description('Oficiālais nosaukums identificē variantu. Filtra krāsas nosaka, kuros kataloga filtros tas parādās.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Oficiālais nosaukums')
                            ->helperText('Saglabā ražotāja vai veikala izmantoto nosaukumu. To netulko.')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (blank($get('code'))) {
                                    $set('code', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('code')
                            ->label('Kods')
                            ->helperText('Mazie burti, cipari un defises. Pēc izveides kodu nemaini.')
                            ->required()
                            ->maxLength(64)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->disabledOn('edit')
                            ->unique(ignoreRecord: true),
                        CheckboxList::make('filterColours')
                            ->label('Filtra krāsas')
                            ->helperText('Izvēlies visas krāsas, kuras redzamas šajā variantā.')
                            ->relationship(
                                'filterColours',
                                'name_lv',
                                fn ($query) => $query
                                    ->where('active', true)
                                    ->orderBy('sort_order'),
                            )
                            ->columns(3)
                            ->bulkToggleable()
                            ->minItems(1)
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->label('Kārtošanas secība')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(32767)
                            ->default(0)
                            ->required(),
                        Toggle::make('active')
                            ->label('Aktīvs')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Oficiālais nosaukums')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Kods')
                    ->searchable(),
                TextColumn::make('filterColours.name_lv')
                    ->label('Filtra krāsas')
                    ->badge()
                    ->separator(', '),
                TextColumn::make('variants_count')
                    ->label('Varianti')
                    ->counts('variants')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktīvs')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Aktīvs'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColours::route('/'),
            'create' => CreateColour::route('/create'),
            'edit' => EditColour::route('/{record}/edit'),
        ];
    }
}
