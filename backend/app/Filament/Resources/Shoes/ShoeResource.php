<?php

namespace App\Filament\Resources\Shoes;

use App\Enums\Audience;
use App\Filament\Resources\Shoes\Pages\CreateShoe;
use App\Filament\Resources\Shoes\Pages\EditShoe;
use App\Filament\Resources\Shoes\Pages\ListShoes;
use App\Filament\Resources\Shoes\RelationManagers\VariantsRelationManager;
use App\Models\Shoe;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class ShoeResource extends Resource
{
    protected static ?string $model = Shoe::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Apavi';

    protected static ?string $modelLabel = 'apavu modelis';

    protected static ?string $pluralModelLabel = 'apavi';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pamatdati')
                    ->schema([
                        Select::make('brand_id')
                            ->label('Zīmols')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Select::make('category_id')
                            ->label('Kategorija')
                            ->relationship('category', 'name_lv')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label('Oficiālais nosaukums')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (blank($get('slug'))) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Adrese')
                            ->helperText('Nemainīga daļa publiskajā adresē.')
                            ->required()
                            ->maxLength(255)
                            ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                            ->unique(ignoreRecord: true),
                        TextInput::make('manufacturer_style_code')
                            ->label('Ražotāja modeļa kods')
                            ->maxLength(100)
                            ->unique(
                                table: 'shoes',
                                column: 'manufacturer_style_code',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where(
                                    'brand_id',
                                    $get('brand_id'),
                                ),
                            ),
                        Select::make('audience')
                            ->label('Auditorija')
                            ->options([
                                Audience::Men->value => 'Vīriešiem',
                                Audience::Women->value => 'Sievietēm',
                                Audience::Unisex->value => 'Unisex',
                                Audience::Kids->value => 'Bērniem',
                            ])
                            ->required(),
                        Toggle::make('active')
                            ->label('Aktīvs')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Apraksti')
                    ->schema([
                        Textarea::make('description_lv')
                            ->label('Apraksts latviski')
                            ->rows(6),
                        Textarea::make('description_en')
                            ->label('Apraksts angliski')
                            ->rows(6),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nosaukums')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->label('Zīmols')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name_lv')
                    ->label('Kategorija')
                    ->sortable(),
                TextColumn::make('audience')
                    ->label('Auditorija')
                    ->formatStateUsing(fn (Audience|string $state): string => match (
                        $state instanceof Audience ? $state : Audience::from($state)
                    ) {
                        Audience::Men => 'Vīriešiem',
                        Audience::Women => 'Sievietēm',
                        Audience::Unisex => 'Unisex',
                        Audience::Kids => 'Bērniem',
                    }),
                TextColumn::make('variants_count')
                    ->label('Varianti')
                    ->counts('variants')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktīvs')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Atjaunināts')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Zīmols')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name_lv')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('audience')
                    ->label('Auditorija')
                    ->options([
                        Audience::Men->value => 'Vīriešiem',
                        Audience::Women->value => 'Sievietēm',
                        Audience::Unisex->value => 'Unisex',
                        Audience::Kids->value => 'Bērniem',
                    ]),
                TernaryFilter::make('active')->label('Aktīvs'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShoes::route('/'),
            'create' => CreateShoe::route('/create'),
            'edit' => EditShoe::route('/{record}/edit'),
        ];
    }
}
