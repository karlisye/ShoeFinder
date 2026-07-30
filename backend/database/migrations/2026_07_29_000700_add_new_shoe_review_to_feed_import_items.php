<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->foreignId('new_shoe_brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();
            $table->foreignId('new_shoe_category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();
            $table->string('new_shoe_name')->nullable();
            $table->string('new_shoe_slug')->nullable();
            $table->string('new_shoe_style_code', 100)->nullable();
            $table->string('new_shoe_audience', 16)->nullable();
            $table->foreignId('created_shoe_id')
                ->nullable()
                ->constrained('shoes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_shoe_id');
            $table->dropConstrainedForeignId('new_shoe_brand_id');
            $table->dropConstrainedForeignId('new_shoe_category_id');
            $table->dropColumn([
                'new_shoe_name',
                'new_shoe_slug',
                'new_shoe_style_code',
                'new_shoe_audience',
            ]);
        });
    }
};
