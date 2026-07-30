<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->string('new_colour_code', 64)->nullable();
            $table->string('new_colour_name_lv')->nullable();
            $table->string('new_colour_name_en')->nullable();
            $table->string('new_manufacturer_variant_code', 100)->nullable();
            $table->foreignId('created_variant_id')
                ->nullable()
                ->constrained('shoe_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_variant_id');
            $table->dropColumn([
                'new_colour_code',
                'new_colour_name_lv',
                'new_colour_name_en',
                'new_manufacturer_variant_code',
            ]);
        });
    }
};
