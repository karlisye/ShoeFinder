<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Kategorijas';

    protected static ?string $modelLabel = 'kategorija';

    protected static ?string $pluralModelLabel = 'kategorijas';

    protected static ?string $recordTitleAttribute = 'name_lv';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kategorijas dati')
                    ->schema([
                        TextInput::make('name_lv')
                            ->label('Nosaukums latviski')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (blank($get('slug'))) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('name_en')
                            ->label('Nosaukums angliski')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label('Adrese')
                            ->helperText('Nemainīga daļa publiskajā adresē.')
                            ->required()
                            ->maxLength(255)
                            ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                            ->unique(ignoreRecord: true),
                        TextInput::make('sort_order')
                            ->label('Kārtošanas secība')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(32767)
                            ->default(0)
                            ->required(),
                        Textarea::make('description_lv')
                            ->label('Apraksts latviski')
                            ->rows(4),
                        Textarea::make('description_en')
                            ->label('Apraksts angliski')
                            ->rows(4),
                        Toggle::make('active')
                            ->label('Aktīva')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_lv')
                    ->label('Nosaukums latviski')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_en')
                    ->label('Nosaukums angliski')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label('Secība')
                    ->sortable(),
                TextColumn::make('shoes_count')
                    ->label('Apavi')
                    ->counts('shoes')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktīva')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Aktīva'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
