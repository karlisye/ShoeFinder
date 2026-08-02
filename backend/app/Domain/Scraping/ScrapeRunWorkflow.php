<?php

namespace App\Domain\Scraping;

use App\Models\ScrapeRun;
use App\Models\ScrapeRunItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

class ScrapeRunWorkflow
{
    public function __construct(
        private readonly ScraperProcess $process,
        private readonly ScrapeResultNormalizer $normalizer,
        private readonly ScrapeChangeSet $changeSet,
    ) {}

    public function preview(ScrapeRun $run): ScrapeRun
    {
        $run = DB::transaction(function () use ($run): ScrapeRun {
            $lockedRun = ScrapeRun::query()->lockForUpdate()->findOrFail($run->getKey());

            if (! in_array($lockedRun->status, [ScrapeRun::STATUS_QUEUED, ScrapeRun::STATUS_SCRAPING], true)) {
                throw new LogicException('This scrape run cannot be previewed.');
            }

            $lockedRun->update([
                'status' => ScrapeRun::STATUS_SCRAPING,
                'started_at' => $lockedRun->started_at ?? now(),
                'errors' => null,
            ]);

            return $lockedRun;
        });

        $pendingItems = $run->items()
            ->with(['retailerListing.retailer', 'retailerListing.variant'])
            ->where('status', ScrapeRunItem::STATUS_PENDING)
            ->get();

        foreach ($pendingItems as $index => $item) {
            $this->previewItem($item);

            if ($index < $pendingItems->count() - 1) {
                usleep((int) config('scraper.crawl_delay_ms', 2000) * 1000);
            }
        }

        $counts = $run->items()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $successfulCount = collect([
            ScrapeRunItem::STATUS_CHANGED,
            ScrapeRunItem::STATUS_UNCHANGED,
            ScrapeRunItem::STATUS_UNAVAILABLE,
        ])->sum(fn (string $status): int => (int) ($counts[$status] ?? 0));
        $failedCount = (int) ($counts[ScrapeRunItem::STATUS_FAILED] ?? 0);
        $changedCount = (int) ($counts[ScrapeRunItem::STATUS_CHANGED] ?? 0)
            + (int) ($counts[ScrapeRunItem::STATUS_UNAVAILABLE] ?? 0);

        $run->update([
            'status' => $successfulCount > 0 ? ScrapeRun::STATUS_READY : ScrapeRun::STATUS_FAILED,
            'successful_count' => $successfulCount,
            'failed_count' => $failedCount,
            'changed_count' => $changedCount,
            'finished_at' => now(),
            'errors' => $successfulCount > 0 ? null : [[
                'code' => 'no_successful_items',
                'message' => 'No listing could be prepared for review.',
            ]],
        ]);

        return $run->refresh();
    }

    private function previewItem(ScrapeRunItem $item): void
    {
        try {
            $result = $this->normalizer->normalize($item, $this->process->scrape($item));
            $changes = $this->changeSet->build($item->baseline, $result);
            $status = match (true) {
                $result['availability'] === 'unavailable' => ScrapeRunItem::STATUS_UNAVAILABLE,
                $changes !== [] => ScrapeRunItem::STATUS_CHANGED,
                default => ScrapeRunItem::STATUS_UNCHANGED,
            };

            $item->update([
                'status' => $status,
                'result_payload' => $result,
                'changes' => $changes === [] ? null : $changes,
                'error' => null,
                'observed_at' => CarbonImmutable::parse($result['observed_at']),
            ]);
        } catch (ScrapeResultException $exception) {
            $item->update([
                'status' => ScrapeRunItem::STATUS_FAILED,
                'error' => [
                    'code' => $exception->errorCode,
                    'message' => $exception->getMessage(),
                    'retryable' => $exception->retryable,
                ],
            ]);
        }
    }
}
