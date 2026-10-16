<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Data demo lengkap (DemoSeeder) sengaja TIDAK dipanggil otomatis —
     * panggil eksplisit per kebutuhan agar test & deploy tidak terbebani:
     *
     *     php artisan migrate:fresh --seed --seeder=DemoSeeder
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
    }
}
