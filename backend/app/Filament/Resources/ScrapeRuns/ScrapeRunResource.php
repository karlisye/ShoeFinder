<?php

namespace App\Filament\Resources\ScrapeRuns;

use App\Filament\Resources\ScrapeRuns\Pages\ListScrapeRuns;
use App\Filament\Resources\ScrapeRuns\Pages\ViewScrapeRun;
use App\Filament\Resources\ScrapeRuns\RelationManagers\ItemsRelationManager;
use App\Models\ScrapeRun;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScrapeRunResource extends Resource
{
    protected static ?string $model = ScrapeRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Scrape runs';

    protected static ?string $modelLabel = 'scrape run';

    protected static ?string $pluralModelLabel = 'Scrape runs';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 7;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Run summary')
                ->schema([
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                        ->color(fn (?string $state): string => self::statusColor($state)),
                    TextEntry::make('retailer.name')
                        ->label('Scope')
                        ->placeholder('All supported retailers'),
                    TextEntry::make('user.email')
                        ->label('Started by')
                        ->placeholder('System'),
                    TextEntry::make('created_at')
                        ->label('Created')
                        ->dateTime('Y-m-d H:i:s'),
                    TextEntry::make('total_count')->label('Listings')->numeric(),
                    TextEntry::make('successful_count')->label('Successful')->numeric(),
                    TextEntry::make('changed_count')->label('With changes')->numeric(),
                    TextEntry::make('failed_count')->label('Failed')->numeric(),
                    TextEntry::make('applied_at')
                        ->label('Applied')
                        ->dateTime('Y-m-d H:i:s')
                        ->placeholder('Not applied'),
                    TextEntry::make('errors')
                        ->label('Run errors')
                        ->formatStateUsing(fn (?array $state): string => collect($state)
                            ->pluck('message')
                            ->implode("\n"))
                        ->visible(fn (?ScrapeRun $record): bool => filled($record?->errors))
                        ->columnSpanFull(),
                ])
                ->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Run')->prefix('#')->sortable(),
                TextColumn::make('retailer.name')
                    ->label('Scope')
                    ->placeholder('All supported retailers')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                    ->color(fn (?string $state): string => self::statusColor($state)),
                TextColumn::make('total_count')->label('Listings')->numeric(),
                TextColumn::make('changed_count')->label('Changes')->numeric(),
                TextColumn::make('failed_count')->label('Failed')->numeric(),
                TextColumn::make('created_at')->label('Created')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make()->label('Open'),
            ])
            ->poll('3s')
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScrapeRuns::route('/'),
            'view' => ViewScrapeRun::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status] ?? 'Unknown';
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            ScrapeRun::STATUS_READY => 'warning',
            ScrapeRun::STATUS_APPLIED => 'success',
            ScrapeRun::STATUS_FAILED, ScrapeRun::STATUS_STALE => 'danger',
            ScrapeRun::STATUS_QUEUED,
            ScrapeRun::STATUS_SCRAPING,
            ScrapeRun::STATUS_APPLY_QUEUED,
            ScrapeRun::STATUS_APPLYING => 'info',
            default => 'gray',
        };
    }

    public static function statusOptions(): array
    {
        return [
            ScrapeRun::STATUS_QUEUED => 'Queued',
            ScrapeRun::STATUS_SCRAPING => 'Scraping product pages',
            ScrapeRun::STATUS_READY => 'Ready for approval',
            ScrapeRun::STATUS_APPLY_QUEUED => 'Approval queued',
            ScrapeRun::STATUS_APPLYING => 'Applying changes',
            ScrapeRun::STATUS_APPLIED => 'Applied',
            ScrapeRun::STATUS_FAILED => 'Failed',
            ScrapeRun::STATUS_STALE => 'Preview is stale',
        ];
    }
}
