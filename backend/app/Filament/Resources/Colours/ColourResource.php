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

    protected static ?string $navigationLabel = 'Colourways';

    protected static ?string $modelLabel = 'colourway';

    protected static ?string $pluralModelLabel = 'Colourways';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Colourway details')
                    ->description('The official name identifies the variant. Filter colours control which catalogue filters include it.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Official name')
                            ->helperText('Use the manufacturer or retailer colourway name. Do not translate it.')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (blank($get('code'))) {
                                    $set('code', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('code')
                            ->label('Code')
                            ->helperText('Use lowercase letters, numbers, and hyphens. Do not change the code after creation.')
                            ->required()
                            ->maxLength(64)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->disabledOn('edit')
                            ->unique(ignoreRecord: true),
                        CheckboxList::make('filterColours')
                            ->label('Filter colours')
                            ->helperText('Select every colour visible in this colourway.')
                            ->relationship(
                                'filterColours',
                                'name_en',
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
                            ->label('Sort order')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(32767)
                            ->default(0)
                            ->required(),
                        Toggle::make('active')
                            ->label('Active')
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
                    ->label('Official name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable(),
                TextColumn::make('filterColours.name_en')
                    ->label('Filter colours')
                    ->badge()
                    ->separator(', '),
                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Active'),
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
