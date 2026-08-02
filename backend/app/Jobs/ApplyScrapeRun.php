<?php

namespace App\Jobs;

use App\Domain\Scraping\ScrapeRunWorkflow;
use App\Models\ScrapeRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ApplyScrapeRun implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly int $scrapeRunId) {}

    public function handle(ScrapeRunWorkflow $workflow): void
    {
        $run = ScrapeRun::query()->find($this->scrapeRunId);

        if ($run === null || ! in_array($run->status, [
            ScrapeRun::STATUS_APPLY_QUEUED,
            ScrapeRun::STATUS_APPLYING,
        ], true)) {
            return;
        }

        $workflow->apply($run);
    }

    public function uniqueId(): string
    {
        return (string) $this->scrapeRunId;
    }

    public function failed(?Throwable $exception): void
    {
        $run = ScrapeRun::query()->find($this->scrapeRunId);

        if ($run === null || ! in_array($run->status, [
            ScrapeRun::STATUS_APPLY_QUEUED,
            ScrapeRun::STATUS_APPLYING,
        ], true)) {
            return;
        }

        $run->update([
            'status' => ScrapeRun::STATUS_READY,
            'errors' => [[
                'code' => 'apply_job_failed',
                'message' => 'The scrape apply worker failed. The preview can be applied again.',
            ]],
        ]);
    }
}
