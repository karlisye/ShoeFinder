<?php

namespace Tests\Feature\Database;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StageOneReferenceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_is_repeatable_and_creates_no_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('colours')
            ->where('code', 'black')
            ->update([
                'name' => 'Wrong',
                'active' => false,
            ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('sizes', 79);
        $this->assertDatabaseCount('colours', 13);
        $this->assertDatabaseCount('users', 0);

        $this->assertDatabaseHas('sizes', [
            'eu_size' => 16.0,
            'label' => '16',
            'sort_order' => 0,
            'active' => true,
        ]);
        $this->assertDatabaseHas('sizes', [
            'eu_size' => 16.5,
            'label' => '16.5',
            'sort_order' => 1,
            'active' => true,
        ]);
        $this->assertDatabaseHas('sizes', [
            'eu_size' => 55.0,
            'label' => '55',
            'sort_order' => 78,
            'active' => true,
        ]);
        $this->assertDatabaseHas('colours', [
            'code' => 'black',
            'name' => 'Black',
            'active' => true,
        ]);
        $this->assertDatabaseHas('colours', [
            'code' => 'multicolour',
            'name' => 'Multicolour',
        ]);
    }
}
