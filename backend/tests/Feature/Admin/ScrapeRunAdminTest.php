<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ScrapeRuns\Pages\ViewScrapeRun;
use App\Filament\Resources\ScrapeRuns\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\ScrapeRuns\ScrapeRunResource;
use App\Filament\Widgets\ScrapeRunsWidget;
use App\Jobs\ApplyScrapeRun;
use App\Jobs\PreviewScrapeRun;
use App\Models\ScrapeRun;
use App\Models\ScrapeRunItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class ScrapeRunAdminTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin_email' => 'admin@example.test']);
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->saveAppAuthenticationSecret('test-totp-secret');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Queue::fake();
    }

    public function test_scrape_run_list_and_preview_pages_render(): void
    {
        $run = ScrapeRun::query()->create([
            'status' => ScrapeRun::STATUS_READY,
            'total_count' => 1,
            'successful_count' => 1,
            'changed_count' => 1,
        ]);
        $run->items()->create([
            'position' => 1,
            'status' => ScrapeRunItem::STATUS_CHANGED,
            'product_url' => 'https://ballzy.eu/en/product/test-shoe',
            'listing_label' => 'Ballzy / Test shoe / Black',
            'baseline' => ['current_price' => '99.99', 'sizes' => []],
            'result_payload' => ['current_price' => '89.99', 'sizes' => []],
            'changes' => ['listing' => [
                'current_price' => ['before' => '99.99', 'after' => '89.99'],
            ]],
        ]);

        $this->get(ScrapeRunResource::getUrl('index'))->assertOk()->assertSee('Scrape runs');
        $this->get(ScrapeRunResource::getUrl('view', ['record' => $run]))
            ->assertOk();
        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $run,
            'pageClass' => ViewScrapeRun::class,
        ])
            ->assertSee('Ballzy / Test shoe / Black');
    }

    public function test_dashboard_action_queues_a_filtered_scrape_run(): void
    {
        $context = $this->createCatalogueContext('scrape-admin-start');
        $context['retailer']->update(['name' => 'Ballzy', 'slug' => 'ballzy']);
        $this->createListing($context['variant'], $context['retailer'], [
            'product_url' => 'https://ballzy.eu/en/product/test-shoe',
        ]);

        Livewire::test(ScrapeRunsWidget::class)
            ->callAction(
                TestAction::make('startScrape')->table(),
                ['scope' => "retailer:{$context['retailer']->id}"],
            )
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(ScrapeRun::class, [
            'retailer_id' => $context['retailer']->id,
            'status' => ScrapeRun::STATUS_QUEUED,
            'total_count' => 1,
        ]);
        Queue::assertPushedOn('scrapes', PreviewScrapeRun::class);
    }

    public function test_ready_preview_can_be_queued_for_approval(): void
    {
        $run = ScrapeRun::query()->create([
            'status' => ScrapeRun::STATUS_READY,
            'total_count' => 1,
            'successful_count' => 1,
        ]);

        Livewire::test(ViewScrapeRun::class, ['record' => $run->getRouteKey()])
            ->callAction('apply')
            ->assertHasNoActionErrors();

        $this->assertSame(ScrapeRun::STATUS_APPLY_QUEUED, $run->refresh()->status);
        Queue::assertPushedOn('scrapes', ApplyScrapeRun::class);
    }

    public function test_ready_preview_can_be_cancelled_without_applying_changes(): void
    {
        $run = ScrapeRun::query()->create([
            'status' => ScrapeRun::STATUS_READY,
            'total_count' => 1,
            'successful_count' => 1,
            'changed_count' => 1,
        ]);

        Livewire::test(ViewScrapeRun::class, ['record' => $run->getRouteKey()])
            ->callAction('cancel')
            ->assertHasNoActionErrors();

        $run->refresh();
        $this->assertSame(ScrapeRun::STATUS_CANCELLED, $run->status);
        $this->assertSame(ScrapeRun::CANCELLATION_MANUAL, $run->cancellation_reason);
        $this->assertNotNull($run->cancelled_at);
        Queue::assertNotPushed(ApplyScrapeRun::class);
    }
}
