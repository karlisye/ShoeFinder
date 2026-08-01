<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\CatalogueCoverageStats;
use App\Filament\Widgets\CatalogueIssueStats;
use App\Filament\Widgets\VariantsNeedingAttentionTable;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class CatalogueHealthDashboardTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin_email' => 'admin@example.test']);

        $user = User::factory()->create([
            'email' => 'admin@example.test',
        ]);
        $user->saveAppAuthenticationSecret('test-totp-secret');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->createCatalogueContext('dashboard-attention');
    }

    public function test_dashboard_registers_and_renders_catalogue_health_widgets(): void
    {
        $widgets = Filament::getPanel('admin')->getWidgets();

        $this->assertContains(CatalogueCoverageStats::class, $widgets);
        $this->assertContains(CatalogueIssueStats::class, $widgets);
        $this->assertContains(VariantsNeedingAttentionTable::class, $widgets);

        Livewire::test(CatalogueCoverageStats::class)
            ->assertSee('Catalogue coverage')
            ->assertSee('Public shoes')
            ->assertSee('Fresh in-stock offers')
            ->assertSee('Retailers with live offers');

        Livewire::test(CatalogueIssueStats::class)
            ->assertSee('Catalogue issues')
            ->assertSee('Stale offers')
            ->assertSee('Variants missing a main image')
            ->assertSee('Shoes without an available offer');

        Livewire::test(VariantsNeedingAttentionTable::class)
            ->assertSee('Variants needing attention')
            ->assertSee('Shoe dashboard-attention')
            ->assertSee('Missing main image')
            ->assertSee('No available offer')
            ->assertSee('Open shoe');
    }
}
