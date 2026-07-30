<?php

namespace App\Filament\Resources\FeedImports;

use App\Filament\Resources\FeedImports\Pages\CreateFeedImport;
use App\Filament\Resources\FeedImports\Pages\EditFeedImport;
use App\Filament\Resources\FeedImports\Pages\ListFeedImports;
use App\Filament\Resources\FeedImports\RelationManagers\ItemsRelationManager;
use App\Models\FeedImport;
use App\Models\Retailer;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeedImportResource extends Resource
{
    protected static ?string $model = FeedImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Importi';

    protected static ?string $modelLabel = 'datu imports';

    protected static ?string $pluralModelLabel = 'datu importi';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Importa fails')
                    ->schema([
                        Select::make('retailer_id')
                            ->label('Veikals')
                            ->options(fn () => Retailer::query()
                                ->whereIn('slug', array_keys(config('feeds.retailers', [])))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->disabled(fn (?FeedImport $record): bool => $record !== null),
                        FileUpload::make('stored_path')
                            ->label('Fails')
                            ->helperText('Atļauti CSV, JSON, JSONL un XML faili līdz 10 MB.')
                            ->disk('local')
                            ->directory('feed-imports')
                            ->visibility('private')
                            ->storeFileNamesIn('original_filename')
                            ->preventFilePathTampering()
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/csv',
                                'application/vnd.ms-excel',
                                'application/json',
                                'text/json',
                                'application/x-ndjson',
                                'application/ndjson',
                                'application/jsonl',
                                'text/jsonl',
                                'application/xml',
                                'text/xml',
                            ])
                            ->mimeTypeMap([
                                'csv' => 'text/csv',
                                'json' => 'application/json',
                                'jsonl' => 'application/x-ndjson',
                                'xml' => 'application/xml',
                            ])
                            ->maxSize(10240)
                            ->required()
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        Placeholder::make('file_name')
                            ->label('Fails')
                            ->content(fn (?FeedImport $record): string => $record?->original_filename ?? 'Nav')
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('status')
                            ->label('Statuss')
                            ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('ready_count')
                            ->label('Gatavi importēšanai')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('review_count')
                            ->label('Jāpārbauda')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('invalid_count')
                            ->label('Nederīgi')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        Textarea::make('errors')
                            ->label('Kļūdas')
                            ->formatStateUsing(fn (?array $state): string => collect($state)
                                ->map(fn (array $error): string => $error['message']
                                    ?? $error['code']
                                    ?? 'Nezināma kļūda')
                                ->implode("\n"))
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->visible(fn (?FeedImport $record): bool => filled($record?->errors)),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('retailer.name')
                    ->label('Veikals')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('original_filename')
                    ->label('Fails')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Statuss')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        FeedImport::STATUS_READY => 'warning',
                        FeedImport::STATUS_APPLIED => 'success',
                        FeedImport::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('ready_count')
                    ->label('Gatavi')
                    ->numeric(),
                TextColumn::make('review_count')
                    ->label('Jāpārbauda')
                    ->numeric(),
                TextColumn::make('invalid_count')
                    ->label('Nederīgi')
                    ->numeric(),
                TextColumn::make('created_at')
                    ->label('Izveidots')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statuss')
                    ->options([
                        FeedImport::STATUS_READY => 'Gatavs pārbaudei',
                        FeedImport::STATUS_APPLIED => 'Importēts',
                        FeedImport::STATUS_FAILED => 'Kļūda',
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Atvērt'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedImports::route('/'),
            'create' => CreateFeedImport::route('/create'),
            'edit' => EditFeedImport::route('/{record}/edit'),
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            FeedImport::STATUS_UPLOADED => 'Fails augšupielādēts',
            FeedImport::STATUS_READY => 'Gatavs pārbaudei',
            FeedImport::STATUS_FAILED => 'Kļūda',
            FeedImport::STATUS_APPLIED => 'Importēts',
            default => 'Nav zināms',
        };
    }
}
