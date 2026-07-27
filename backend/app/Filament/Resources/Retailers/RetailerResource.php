<?php

namespace App\Filament\Resources\Retailers;

use App\Filament\Resources\Retailers\Pages\CreateRetailer;
use App\Filament\Resources\Retailers\Pages\EditRetailer;
use App\Filament\Resources\Retailers\Pages\ListRetailers;
use App\Models\Retailer;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class RetailerResource extends Resource
{
    protected static ?string $model = Retailer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Veikali';

    protected static ?string $modelLabel = 'veikals';

    protected static ?string $pluralModelLabel = 'veikali';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Veikala dati')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nosaukums')
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
                        TextInput::make('website_url')
                            ->label('Tīmekļvietne')
                            ->url()
                            ->maxLength(2048),
                        FileUpload::make('logo_path')
                            ->label('Logotips')
                            ->disk('public')
                            ->directory('retailers')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048),
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
                ImageColumn::make('logo_path')
                    ->label('Logotips')
                    ->disk('public')
                    ->height(32),
                TextColumn::make('name')
                    ->label('Nosaukums')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('listings_count')
                    ->label('Piedāvājumi')
                    ->counts('listings')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktīvs')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Atjaunināts')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
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
            'index' => ListRetailers::route('/'),
            'create' => CreateRetailer::route('/create'),
            'edit' => EditRetailer::route('/{record}/edit'),
        ];
    }
}
