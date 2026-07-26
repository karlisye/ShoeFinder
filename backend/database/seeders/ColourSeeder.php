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
            ['code' => 'black', 'name_lv' => 'Melna', 'name_en' => 'Black'],
            ['code' => 'white', 'name_lv' => 'Balta', 'name_en' => 'White'],
            ['code' => 'grey', 'name_lv' => 'Pelēka', 'name_en' => 'Grey'],
            ['code' => 'beige', 'name_lv' => 'Bēša', 'name_en' => 'Beige'],
            ['code' => 'brown', 'name_lv' => 'Brūna', 'name_en' => 'Brown'],
            ['code' => 'red', 'name_lv' => 'Sarkana', 'name_en' => 'Red'],
            ['code' => 'orange', 'name_lv' => 'Oranža', 'name_en' => 'Orange'],
            ['code' => 'yellow', 'name_lv' => 'Dzeltena', 'name_en' => 'Yellow'],
            ['code' => 'green', 'name_lv' => 'Zaļa', 'name_en' => 'Green'],
            ['code' => 'blue', 'name_lv' => 'Zila', 'name_en' => 'Blue'],
            ['code' => 'purple', 'name_lv' => 'Violeta', 'name_en' => 'Purple'],
            ['code' => 'pink', 'name_lv' => 'Rozā', 'name_en' => 'Pink'],
            ['code' => 'multicolour', 'name_lv' => 'Daudzkrāsu', 'name_en' => 'Multicolour'],
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
            ['name_lv', 'name_en', 'sort_order', 'active', 'updated_at'],
        );
    }
}
