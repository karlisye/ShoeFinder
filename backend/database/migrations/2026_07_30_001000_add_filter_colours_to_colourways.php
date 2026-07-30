<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filter_colours', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name_lv');
            $table->string('name_en');
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('colour_filter_colour', function (Blueprint $table): void {
            $table->foreignId('colour_id')
                ->constrained('colours')
                ->cascadeOnDelete();
            $table->foreignId('filter_colour_id')
                ->constrained('filter_colours')
                ->restrictOnDelete();
            $table->timestampsTz();

            $table->primary(['colour_id', 'filter_colour_id']);
        });

        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->json('new_filter_colour_ids')->nullable();
        });

        $timestamp = now();
        $filterColours = [
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
            ['code' => 'silver', 'name_lv' => 'Sudraba', 'name_en' => 'Silver'],
            ['code' => 'gold', 'name_lv' => 'Zelta', 'name_en' => 'Gold'],
            ['code' => 'multicolour', 'name_lv' => 'Daudzkrāsu', 'name_en' => 'Multicolour'],
        ];

        DB::table('filter_colours')->insert(array_map(
            fn (array $colour, int $index): array => [
                ...$colour,
                'sort_order' => $index,
                'active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $filterColours,
            array_keys($filterColours),
        ));

        $filterIds = DB::table('filter_colours')->pluck('id', 'code');
        $codesFor = static function (string $value): array {
            $value = mb_strtolower($value);
            $terms = [
                'black' => ['black'],
                'white' => ['white', 'egret', 'chalk'],
                'grey' => ['grey', 'gray', 'anthracite', 'graphite', 'lunar rock'],
                'beige' => ['beige', 'cream', 'vanilla', 'sail', 'taupe', 'oyster', 'phantom', 'orewood', 'bone'],
                'brown' => ['brown', 'gum'],
                'red' => ['red', 'burgundy'],
                'orange' => ['orange'],
                'yellow' => ['yellow'],
                'green' => ['green'],
                'blue' => ['blue', 'navy'],
                'purple' => ['purple', 'violet'],
                'pink' => ['pink', 'rose'],
                'silver' => ['silver', 'metallic'],
                'gold' => ['gold'],
                'multicolour' => ['multicolour', 'multi-color', 'multi colour', 'rainbow'],
            ];

            return array_keys(array_filter(
                $terms,
                fn (array $needles): bool => collect($needles)
                    ->contains(fn (string $needle): bool => str_contains($value, $needle)),
            ));
        };

        foreach (DB::table('colours')->get(['id', 'code', 'name']) as $colour) {
            foreach ($codesFor("{$colour->code} {$colour->name}") as $code) {
                DB::table('colour_filter_colour')->insertOrIgnore([
                    'colour_id' => $colour->id,
                    'filter_colour_id' => $filterIds[$code],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }

        foreach (
            DB::table('feed_import_items')
                ->whereNotNull('new_colour_code')
                ->whereNotNull('new_colour_name')
                ->get(['id', 'new_colour_code', 'new_colour_name']) as $item
        ) {
            $ids = collect($codesFor(
                "{$item->new_colour_code} {$item->new_colour_name}",
            ))
                ->map(fn (string $code): int => $filterIds[$code])
                ->values()
                ->all();

            if ($ids !== []) {
                DB::table('feed_import_items')
                    ->where('id', $item->id)
                    ->update([
                        'new_filter_colour_ids' => json_encode($ids),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->dropColumn('new_filter_colour_ids');
        });
        Schema::dropIfExists('colour_filter_colour');
        Schema::dropIfExists('filter_colours');
    }
};
