<?php

namespace App\Jobs;

use App\Domain\Feeds\FeedImportWorkflow;
use App\Models\FeedImport;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApplyFeedImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $feedImportId) {}

    public function handle(FeedImportWorkflow $workflow): void
    {
        $feedImport = $this->begin();

        if ($feedImport === null) {
            return;
        }

        $workflow->apply($feedImport);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function uniqueId(): string
    {
        return (string) $this->feedImportId;
    }

    public function failed(?Throwable $exception): void
    {
        $feedImport = FeedImport::query()->find($this->feedImportId);

        if (
            $feedImport === null
            || ! in_array($feedImport->status, [
                FeedImport::STATUS_APPLY_QUEUED,
                FeedImport::STATUS_APPLYING,
            ], true)
        ) {
            return;
        }

        $feedImport->update([
            'status' => FeedImport::STATUS_FAILED,
            'errors' => [[
                'code' => 'apply_job_failed',
                'message' => $exception?->getMessage()
                    ?? 'The import worker stopped unexpectedly.',
            ]],
        ]);
    }

    private function begin(): ?FeedImport
    {
        return DB::transaction(function (): ?FeedImport {
            $feedImport = FeedImport::query()
                ->lockForUpdate()
                ->find($this->feedImportId);

            if ($feedImport === null) {
                return null;
            }

            $canStart = $feedImport->status === FeedImport::STATUS_APPLY_QUEUED;
            $canRetry = $feedImport->status === FeedImport::STATUS_APPLYING
                && $this->attempts() > 1;
            $canRetryFailedJob = $feedImport->status === FeedImport::STATUS_FAILED;

            if (! $canStart && ! $canRetry && ! $canRetryFailedJob) {
                return null;
            }

            $feedImport->update([
                'status' => FeedImport::STATUS_APPLYING,
                'errors' => null,
            ]);

            return $feedImport;
        });
    }
}
