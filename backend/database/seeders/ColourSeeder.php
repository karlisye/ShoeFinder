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

        $colourIds = DB::table('colours')
            ->whereIn('code', array_column($colours, 'code'))
            ->pluck('id', 'code');
        $filterColourIds = DB::table('filter_colours')
            ->whereIn('code', array_column($colours, 'code'))
            ->pluck('id', 'code');
        $pivotRows = collect($colours)
            ->filter(fn (array $colour): bool => isset(
                $colourIds[$colour['code']],
                $filterColourIds[$colour['code']],
            ))
            ->map(fn (array $colour): array => [
                'colour_id' => $colourIds[$colour['code']],
                'filter_colour_id' => $filterColourIds[$colour['code']],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->all();

        DB::table('colour_filter_colour')->upsert(
            $pivotRows,
            ['colour_id', 'filter_colour_id'],
            ['updated_at'],
        );
    }
}
