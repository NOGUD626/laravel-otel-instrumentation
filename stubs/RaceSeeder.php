<?php

namespace Database\Seeders;

use App\Models\Race;
use Illuminate\Database\Seeder;

class RaceSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['name' => 'Sample Marathon A',  'prefecture' => 'Tokyo',    'held_on' => '2026-10-12', 'distance_m' => 42195],
            ['name' => 'Sample Marathon B',  'prefecture' => 'Osaka',    'held_on' => '2026-11-23', 'distance_m' => 42195],
            ['name' => 'Sample Half C',      'prefecture' => 'Aichi',    'held_on' => '2026-12-05', 'distance_m' => 21097],
            ['name' => 'Sample 10K D',       'prefecture' => 'Fukuoka',  'held_on' => '2027-01-15', 'distance_m' => 10000],
            ['name' => 'Sample Ekiden E',    'prefecture' => 'Hokkaido', 'held_on' => '2027-02-20', 'distance_m' => 30000],
        ];

        foreach ($samples as $row) {
            Race::firstOrCreate(['name' => $row['name']], $row);
        }
    }
}
