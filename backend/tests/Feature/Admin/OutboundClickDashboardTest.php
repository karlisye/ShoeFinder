<?php

namespace Tests\Feature\Admin;

use App\Domain\Analytics\OutboundClickMetrics;
use App\Filament\Widgets\OutboundClickStats;
use App\Filament\Widgets\OutboundClickTrendChart;
use App\Filament\Widgets\TopRetailersByClicksChart;
use App\Models\OutboundClick;
use App\Models\RetailerListing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class OutboundClickDashboardTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-07-31 12:00:00 UTC');
        CarbonImmutable::setTestNow($this->now);
        config(['app.admin_email' => 'admin@example.test']);
        $this->actingAs(User::factory()->create([
            'email' => 'admin@example.test',
        ]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->createClickHistory();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_metrics_summarize_periods_trends_and_top_retailers(): void
    {
        $metrics = app(OutboundClickMetrics::class);

        $this->assertSame([
            'today' => 2,
            'yesterday' => 1,
            'last_7_days' => 7,
            'previous_7_days' => 3,
            'last_30_days' => 10,
            'previous_30_days' => 5,
            'all_time' => 15,
        ], $metrics->summary($this->now));

        $this->assertSame([
            'labels' => [
                'Jul 25',
                'Jul 26',
                'Jul 27',
                'Jul 28',
                'Jul 29',
                'Jul 30',
                'Jul 31',
            ],
            'values' => [0, 0, 0, 4, 0, 1, 2],
        ], $metrics->dailyTrend(7, $this->now));

        $this->assertSame([
            [
                'retailer' => 'Retailer dashboard',
                'clicks' => 6,
            ],
            [
                'retailer' => 'Retailer dashboard-second',
                'clicks' => 4,
            ],
        ], $metrics->topRetailers(30, 5, $this->now));
    }

    public function test_dashboard_registers_and_renders_click_widgets(): void
    {
        $widgets = Filament::getPanel('admin')->getWidgets();

        $this->assertContains(OutboundClickStats::class, $widgets);
        $this->assertContains(OutboundClickTrendChart::class, $widgets);
        $this->assertContains(TopRetailersByClicksChart::class, $widgets);
        $this->assertNotContains(AccountWidget::class, $widgets);

        Livewire::test(OutboundClickStats::class)
            ->assertSee('Outbound clicks')
            ->assertSee('Last 30 days')
            ->assertSee('All time');

        Livewire::test(OutboundClickTrendChart::class)
            ->assertSee('Clicks over the last 30 days');

        Livewire::test(TopRetailersByClicksChart::class)
            ->assertSee('Top retailers');
    }

    private function createClickHistory(): void
    {
        $context = $this->createCatalogueContext('dashboard');
        $firstListing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );
        $secondRetailer = $this->createRetailer('dashboard-second');
        $secondListing = $this->createListing(
            $context['variant'],
            $secondRetailer,
        );

        $this->createClicks($firstListing, 2, $this->now->subHours(2));
        $this->createClicks($firstListing, 4, $this->now->subDays(3));
        $this->createClicks($firstListing, 5, $this->now->subDays(31));
        $this->createClicks($secondListing, 1, $this->now->subDay());
        $this->createClicks($secondListing, 3, $this->now->subDays(8));
    }

    private function createClicks(
        RetailerListing $listing,
        int $count,
        CarbonImmutable $clickedAt,
    ): void {
        for ($index = 0; $index < $count; $index++) {
            OutboundClick::create([
                'retailer_listing_id' => $listing->id,
                'locale' => $index % 2 === 0 ? 'lv' : 'en',
                'referrer_path' => '/shoes/dashboard-shoe',
                'clicked_at' => $clickedAt->addMinutes($index),
            ]);
        }
    }
}
