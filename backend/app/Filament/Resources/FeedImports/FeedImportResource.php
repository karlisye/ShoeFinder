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

    protected static ?string $navigationLabel = 'Imports';

    protected static ?string $modelLabel = 'feed import';

    protected static ?string $pluralModelLabel = 'Feed imports';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Import file')
                    ->schema([
                        Select::make('retailer_id')
                            ->label('Retailer')
                            ->options(fn () => Retailer::query()
                                ->whereIn('slug', array_keys(config('feeds.retailers', [])))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->disabled(fn (?FeedImport $record): bool => $record !== null),
                        FileUpload::make('stored_path')
                            ->label('File')
                            ->helperText('CSV, JSON, JSONL, and XML files up to 10 MB.')
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
                            ->label('File')
                            ->content(fn (?FeedImport $record): string => $record?->original_filename ?? 'None')
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('ready_count')
                            ->label('Ready to import')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('review_count')
                            ->label('Needs review')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('invalid_count')
                            ->label('Invalid')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        Textarea::make('errors')
                            ->label('Errors')
                            ->formatStateUsing(fn (?array $state): string => collect($state)
                                ->map(fn (array $error): string => $error['message']
                                    ?? $error['code']
                                    ?? 'Unknown error')
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
                    ->label('Retailer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        FeedImport::STATUS_READY => 'warning',
                        FeedImport::STATUS_APPLIED => 'success',
                        FeedImport::STATUS_FAILED => 'danger',
                        FeedImport::STATUS_PREVIEW_QUEUED,
                        FeedImport::STATUS_PREVIEWING,
                        FeedImport::STATUS_APPLY_QUEUED,
                        FeedImport::STATUS_APPLYING => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('ready_count')
                    ->label('Ready')
                    ->numeric(),
                TextColumn::make('review_count')
                    ->label('Needs review')
                    ->numeric(),
                TextColumn::make('invalid_count')
                    ->label('Invalid')
                    ->numeric(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        FeedImport::STATUS_PREVIEW_QUEUED => 'Preview queued',
                        FeedImport::STATUS_PREVIEWING => 'Preparing preview',
                        FeedImport::STATUS_READY => 'Ready for review',
                        FeedImport::STATUS_APPLY_QUEUED => 'Import queued',
                        FeedImport::STATUS_APPLYING => 'Importing',
                        FeedImport::STATUS_APPLIED => 'Imported',
                        FeedImport::STATUS_FAILED => 'Failed',
                    ]),
            ])
            ->poll('3s')
            ->recordActions([
                EditAction::make()->label('Open'),
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
            FeedImport::STATUS_UPLOADED => 'File uploaded',
            FeedImport::STATUS_PREVIEW_QUEUED => 'Preview queued',
            FeedImport::STATUS_PREVIEWING => 'Preparing preview',
            FeedImport::STATUS_READY => 'Ready for review',
            FeedImport::STATUS_APPLY_QUEUED => 'Import queued',
            FeedImport::STATUS_APPLYING => 'Importing',
            FeedImport::STATUS_FAILED => 'Failed',
            FeedImport::STATUS_APPLIED => 'Imported',
            default => 'Unknown',
        };
    }
}
