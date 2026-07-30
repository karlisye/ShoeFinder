<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->foreignId('selected_colour_id')
                ->nullable()
                ->constrained('colours')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feed_import_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('selected_colour_id');
        });
    }
};
