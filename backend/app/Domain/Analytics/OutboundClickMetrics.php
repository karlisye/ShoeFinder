<?php

namespace App\Domain\Analytics;

use App\Models\OutboundClick;
use Carbon\CarbonImmutable;

class OutboundClickMetrics
{
    /**
     * @return array{
     *     today: int,
     *     yesterday: int,
     *     last_7_days: int,
     *     previous_7_days: int,
     *     last_30_days: int,
     *     previous_30_days: int,
     *     all_time: int
     * }
     */
    public function summary(?CarbonImmutable $at = null): array
    {
        $now = $at ?? CarbonImmutable::now();

        return [
            'today' => $this->countBetween($now->startOfDay(), $now),
            'yesterday' => $this->countBetween(
                $now->subDay()->startOfDay(),
                $now->subDay()->endOfDay(),
            ),
            'last_7_days' => $this->countBetween(
                $now->subDays(6)->startOfDay(),
                $now,
            ),
            'previous_7_days' => $this->countBetween(
                $now->subDays(13)->startOfDay(),
                $now->subDays(7)->endOfDay(),
            ),
            'last_30_days' => $this->countBetween(
                $now->subDays(29)->startOfDay(),
                $now,
            ),
            'previous_30_days' => $this->countBetween(
                $now->subDays(59)->startOfDay(),
                $now->subDays(30)->endOfDay(),
            ),
            'all_time' => OutboundClick::query()->count(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function dailyTrend(
        int $days = 30,
        ?CarbonImmutable $at = null,
    ): array {
        $days = max(1, min($days, 365));
        $now = $at ?? CarbonImmutable::now();
        $start = $now->subDays($days - 1)->startOfDay();
        $counts = OutboundClick::query()
            ->whereBetween('clicked_at', [$start, $now])
            ->selectRaw('DATE(clicked_at) AS click_date, COUNT(*) AS click_count')
            ->groupByRaw('DATE(clicked_at)')
            ->orderBy('click_date')
            ->pluck('click_count', 'click_date')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
        $labels = [];
        $values = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->addDays($offset);
            $key = $date->toDateString();
            $labels[] = $date->format('M j');
            $values[] = $counts[$key] ?? 0;
        }

        return compact('labels', 'values');
    }

    /**
     * @return array<int, array{retailer: string, clicks: int}>
     */
    public function topRetailers(
        int $days = 30,
        int $limit = 5,
        ?CarbonImmutable $at = null,
    ): array {
        $days = max(1, min($days, 365));
        $limit = max(1, min($limit, 20));
        $now = $at ?? CarbonImmutable::now();

        return OutboundClick::query()
            ->join(
                'retailer_listings',
                'retailer_listings.id',
                '=',
                'outbound_clicks.retailer_listing_id',
            )
            ->join(
                'retailers',
                'retailers.id',
                '=',
                'retailer_listings.retailer_id',
            )
            ->whereBetween('outbound_clicks.clicked_at', [
                $now->subDays($days - 1)->startOfDay(),
                $now,
            ])
            ->select('retailers.name as retailer_name')
            ->selectRaw('COUNT(outbound_clicks.id) AS click_count')
            ->groupBy('retailers.id', 'retailers.name')
            ->orderByDesc('click_count')
            ->orderBy('retailers.name')
            ->limit($limit)
            ->get()
            ->map(fn (OutboundClick $row): array => [
                'retailer' => (string) $row->retailer_name,
                'clicks' => (int) $row->click_count,
            ])
            ->all();
    }

    private function countBetween(
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): int {
        return OutboundClick::query()
            ->whereBetween('clicked_at', [$start, $end])
            ->count();
    }
}
