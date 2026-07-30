<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ColourSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();
        $colours = [
            ['code' => 'black', 'name' => 'Black'],
            ['code' => 'white', 'name' => 'White'],
            ['code' => 'grey', 'name' => 'Grey'],
            ['code' => 'beige', 'name' => 'Beige'],
            ['code' => 'brown', 'name' => 'Brown'],
            ['code' => 'red', 'name' => 'Red'],
            ['code' => 'orange', 'name' => 'Orange'],
            ['code' => 'yellow', 'name' => 'Yellow'],
            ['code' => 'green', 'name' => 'Green'],
            ['code' => 'blue', 'name' => 'Blue'],
            ['code' => 'purple', 'name' => 'Purple'],
            ['code' => 'pink', 'name' => 'Pink'],
            ['code' => 'multicolour', 'name' => 'Multicolour'],
        ];

        $rows = array_map(
            fn (array $colour, int $index): array => [
                ...$colour,
                'sort_order' => $index,
                'active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $colours,
            array_keys($colours),
        );

        DB::table('colours')->upsert(
            $rows,
            ['code'],
            ['name', 'sort_order', 'active', 'updated_at'],
        );
    }
}
