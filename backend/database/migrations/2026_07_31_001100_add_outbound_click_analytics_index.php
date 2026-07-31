<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbound_clicks', function (Blueprint $table): void {
            $table->index(
                'clicked_at',
                'outbound_click_clicked_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('outbound_clicks', function (Blueprint $table): void {
            $table->dropIndex('outbound_click_clicked_index');
        });
    }
};
