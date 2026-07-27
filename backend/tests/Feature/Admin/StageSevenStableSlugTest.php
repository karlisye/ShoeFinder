<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Shoes\Pages\EditShoe;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class StageSevenStableSlugTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin_email' => 'admin@example.test']);

        $this->actingAs(User::factory()->create([
            'email' => 'admin@example.test',
        ]));

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_existing_shoe_slug_cannot_be_changed_from_admin(): void
    {
        $context = $this->createCatalogueContext('stable-slug');

        Livewire::test(EditShoe::class, [
            'record' => $context['shoe']->getRouteKey(),
        ])
            ->fillForm([
                'slug' => 'changed-public-address',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'shoe-stable-slug',
            $context['shoe']->refresh()->slug,
        );
    }
}
