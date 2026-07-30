<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colours', function (Blueprint $table): void {
            $table->string('name')->nullable();
        });
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->string('new_colour_name')->nullable();
        });

        DB::table('colours')->update([
            'name' => DB::raw('COALESCE(name_en, name_lv)'),
        ]);
        DB::table('feed_import_items')->update([
            'new_colour_name' => DB::raw(
                'COALESCE(new_colour_name_en, new_colour_name_lv)',
            ),
        ]);

        Schema::table('colours', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
            $table->dropColumn(['name_lv', 'name_en']);
        });
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->dropColumn([
                'new_colour_name_lv',
                'new_colour_name_en',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('colours', function (Blueprint $table): void {
            $table->string('name_lv')->nullable();
            $table->string('name_en')->nullable();
        });
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->string('new_colour_name_lv')->nullable();
            $table->string('new_colour_name_en')->nullable();
        });

        DB::table('colours')->update([
            'name_lv' => DB::raw('name'),
            'name_en' => DB::raw('name'),
        ]);
        DB::table('feed_import_items')->update([
            'new_colour_name_lv' => DB::raw('new_colour_name'),
            'new_colour_name_en' => DB::raw('new_colour_name'),
        ]);

        Schema::table('colours', function (Blueprint $table): void {
            $table->string('name_lv')->nullable(false)->change();
            $table->string('name_en')->nullable(false)->change();
            $table->dropColumn('name');
        });
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->dropColumn('new_colour_name');
        });
    }
};
