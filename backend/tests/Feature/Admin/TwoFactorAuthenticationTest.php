<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin_email' => 'admin@example.test']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_user_stores_hidden_encrypted_app_authentication_data(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'app_authentication_secret',
            'app_authentication_recovery_codes',
        ]));

        $user = User::factory()->create([
            'email' => 'admin@example.test',
        ]);

        $this->assertInstanceOf(HasAppAuthentication::class, $user);
        $this->assertInstanceOf(HasAppAuthenticationRecovery::class, $user);

        $user->saveAppAuthenticationSecret('test-totp-secret');
        $user->saveAppAuthenticationRecoveryCodes([
            'hashed-recovery-code',
        ]);
        $user->refresh();

        $stored = DB::table('users')->find($user->id);

        $this->assertSame('test-totp-secret', $user->getAppAuthenticationSecret());
        $this->assertSame(
            ['hashed-recovery-code'],
            $user->getAppAuthenticationRecoveryCodes(),
        );
        $this->assertNotSame(
            'test-totp-secret',
            $stored->app_authentication_secret,
        );
        $this->assertNotSame(
            '["hashed-recovery-code"]',
            $stored->app_authentication_recovery_codes,
        );
        $this->assertArrayNotHasKey(
            'app_authentication_secret',
            $user->toArray(),
        );
        $this->assertArrayNotHasKey(
            'app_authentication_recovery_codes',
            $user->toArray(),
        );
    }

    public function test_admin_panel_requires_recoverable_app_authentication(): void
    {
        $panel = Filament::getPanel('admin');
        $providers = $panel->getMultiFactorAuthenticationProviders();

        $this->assertTrue($panel->hasProfile());
        $this->assertSame(EditProfile::class, $panel->getProfilePage());
        $this->assertTrue($panel->isMultiFactorAuthenticationRequired());
        $this->assertArrayHasKey('app', $providers);
        $this->assertInstanceOf(AppAuthentication::class, $providers['app']);
        $this->assertTrue($providers['app']->isRecoverable());
    }

    public function test_admin_without_app_authentication_is_sent_to_required_setup(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(
                Filament::getPanel('admin')
                    ->getSetUpRequiredMultiFactorAuthenticationUrl(),
            );
    }

    public function test_admin_profile_keeps_the_allowlisted_email_read_only(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
        ]);
        $user->saveAppAuthenticationSecret('test-totp-secret');
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->assertFormFieldIsDisabled('email');
    }

    public function test_admin_with_app_authentication_can_reach_the_panel(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
        ]);
        $user->saveAppAuthenticationSecret('test-totp-secret');

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }
}
