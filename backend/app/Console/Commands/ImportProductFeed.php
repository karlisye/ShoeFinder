<?php

namespace App\Console\Commands;

use App\Domain\Feeds\FeedImporter;
use App\Models\Retailer;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command as CommandStatus;

class ImportProductFeed extends Command
{
    protected $signature = 'feeds:import
        {retailer : Configured feed retailer slug}
        {path : Absolute, backend-relative, or fixture-relative feed path}
        {--apply : Apply accepted changes to the database}';

    protected $description = 'Preview or apply a retailer product feed';

    public function handle(FeedImporter $importer): int
    {
        $feedRetailer = (string) $this->argument('retailer');
        $path = $this->resolvePath((string) $this->argument('path'));

        if ($path === null) {
            $this->error('Feed file not found.');

            return CommandStatus::FAILURE;
        }

        if (config("feeds.retailers.{$feedRetailer}") === null) {
            $this->error("Unknown feed retailer: {$feedRetailer}");

            return CommandStatus::FAILURE;
        }

        $retailer = Retailer::query()->where('slug', $feedRetailer)->first();

        if ($retailer === null) {
            $this->error("Create the retailer '{$feedRetailer}' before importing its feed.");

            return CommandStatus::FAILURE;
        }

        try {
            $report = $importer->import(
                $retailer,
                $feedRetailer,
                $path,
                (bool) $this->option('apply'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return CommandStatus::FAILURE;
        }

        $requestedApply = (bool) $this->option('apply');
        $mode = $report->applied ? 'APPLIED' : 'DRY RUN';
        $this->newLine();
        $this->info("{$mode}: {$feedRetailer}");
        $this->line($path);

        $this->table(
            ['Record', 'Identity', 'Outcome', 'Reason'],
            array_map(
                fn ($item): array => [
                    $item->record ?? '-',
                    $item->identity,
                    $item->outcome,
                    $item->reason,
                ],
                $report->items,
            ),
        );

        $this->table(
            ['Outcome', 'Count'],
            collect($report->counts())
                ->map(fn (int $count, string $outcome): array => [$outcome, $count])
                ->values()
                ->all(),
        );

        if ($report->issues !== []) {
            $this->error('Feed contains invalid records.');
            $this->table(
                ['Record', 'Field', 'Code', 'Message'],
                array_map(
                    fn ($issue): array => [
                        $issue->record ?? '-',
                        $issue->field ?? '-',
                        $issue->code,
                        $issue->message,
                    ],
                    $report->issues,
                ),
            );

            if ($requestedApply) {
                $this->warn('No changes were applied.');
            }

            return CommandStatus::FAILURE;
        }

        if ($requestedApply && ! $report->applied) {
            $this->warn('No changes were applied.');

            return CommandStatus::FAILURE;
        }

        if (! $requestedApply) {
            $this->comment('Run again with --apply to save accepted changes.');
        }

        return CommandStatus::SUCCESS;
    }

    private function resolvePath(string $path): ?string
    {
        $candidates = str_starts_with($path, '/')
            ? [$path]
            : [
                base_path($path),
                base_path("tests/Fixtures/ProductFeeds/{$path}"),
            ];

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);

            if ($resolved !== false && is_file($resolved) && is_readable($resolved)) {
                return $resolved;
            }
        }

        return null;
    }
}
