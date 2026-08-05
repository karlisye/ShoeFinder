<?php

namespace App\Jobs;

use App\Domain\Scraping\ScrapeRunWorkflow;
use App\Models\ScrapeRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PreviewScrapeRun implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public readonly int $scrapeRunId) {}

    public function handle(ScrapeRunWorkflow $workflow): void
    {
        $run = ScrapeRun::query()->find($this->scrapeRunId);

        if ($run === null || ! in_array($run->status, [
            ScrapeRun::STATUS_QUEUED,
            ScrapeRun::STATUS_SCRAPING,
        ], true)) {
            return;
        }

        $workflow->preview($run);
    }

    public function uniqueId(): string
    {
        return (string) $this->scrapeRunId;
    }

    public function failed(?Throwable $exception): void
    {
        $run = ScrapeRun::query()->find($this->scrapeRunId);

        if ($run === null || ! in_array($run->status, [
            ScrapeRun::STATUS_QUEUED,
            ScrapeRun::STATUS_SCRAPING,
        ], true)) {
            return;
        }

        $run->update([
            'status' => ScrapeRun::STATUS_FAILED,
            'finished_at' => now(),
            'errors' => [[
                'code' => 'preview_job_failed',
                'message' => 'The scrape preview worker failed before completing the run.',
            ]],
        ]);
    }
}
