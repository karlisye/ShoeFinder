<?php

namespace App\Domain\Feeds;

use App\Jobs\ApplyFeedImport;
use App\Jobs\PreviewFeedImport;
use App\Models\FeedImport;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class FeedImportQueue
{
    public function preview(FeedImport $feedImport): FeedImport
    {
        $feedImport = DB::transaction(function () use ($feedImport): FeedImport {
            $lockedImport = FeedImport::query()
                ->lockForUpdate()
                ->findOrFail($feedImport->getKey());

            if (! in_array($lockedImport->status, [
                FeedImport::STATUS_UPLOADED,
                FeedImport::STATUS_FAILED,
            ], true)) {
                throw new LogicException('This feed preview is already queued or completed.');
            }

            $lockedImport->update([
                'status' => FeedImport::STATUS_PREVIEW_QUEUED,
                'errors' => null,
            ]);

            return $lockedImport;
        });

        try {
            PreviewFeedImport::dispatch($feedImport->getKey())
                ->onQueue($this->queue());
        } catch (Throwable $exception) {
            $this->markPreviewDispatchFailure($feedImport->getKey(), $exception);

            throw $exception;
        }

        return $feedImport->refresh();
    }

    public function apply(FeedImport $feedImport): FeedImport
    {
        $feedImport = DB::transaction(function () use ($feedImport): FeedImport {
            $lockedImport = FeedImport::query()
                ->lockForUpdate()
                ->findOrFail($feedImport->getKey());

            if (! $lockedImport->canApply()) {
                throw new LogicException('The import is not ready to apply.');
            }

            $lockedImport->update([
                'status' => FeedImport::STATUS_APPLY_QUEUED,
                'errors' => null,
            ]);

            return $lockedImport;
        });

        try {
            ApplyFeedImport::dispatch($feedImport->getKey())
                ->onQueue($this->queue());
        } catch (Throwable $exception) {
            $this->markApplyDispatchFailure($feedImport->getKey(), $exception);

            throw $exception;
        }

        return $feedImport->refresh();
    }

    private function markPreviewDispatchFailure(
        int $feedImportId,
        Throwable $exception,
    ): void {
        $feedImport = FeedImport::query()->find($feedImportId);

        if ($feedImport?->status !== FeedImport::STATUS_PREVIEW_QUEUED) {
            return;
        }

        $feedImport->update([
            'status' => FeedImport::STATUS_FAILED,
            'errors' => [[
                'code' => 'preview_queue_failed',
                'message' => $exception->getMessage(),
            ]],
        ]);
    }

    private function markApplyDispatchFailure(
        int $feedImportId,
        Throwable $exception,
    ): void {
        $feedImport = FeedImport::query()->find($feedImportId);

        if ($feedImport?->status !== FeedImport::STATUS_APPLY_QUEUED) {
            return;
        }

        $feedImport->update([
            'status' => FeedImport::STATUS_READY,
            'errors' => [[
                'code' => 'apply_queue_failed',
                'message' => $exception->getMessage(),
            ]],
        ]);
    }

    private function queue(): string
    {
        return (string) config('feeds.admin.queue', 'imports');
    }
}
