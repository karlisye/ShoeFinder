<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();
        $rows = [];

        for ($step = 0; $step <= 78; $step++) {
            $size = 16 + ($step / 2);
            $rows[] = [
                'eu_size' => number_format($size, 1, '.', ''),
                'label' => $step % 2 === 0
                    ? (string) (int) $size
                    : number_format($size, 1, '.', ''),
                'sort_order' => $step,
                'active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('sizes')->upsert(
            $rows,
            ['eu_size'],
            ['label', 'sort_order', 'active', 'updated_at'],
        );
    }
}
