<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SubscriptionPriceSeeder::class,
            AdminUserSeeder::class,
            CenterBranchCircleSeeder::class,
            SupervisorSeeder::class,
            StudentImportSeeder::class,
            StudentRosterSeeder::class,
            StudentItqanSeeder::class,
            SurahSeeder::class,
            RecommendationTemplateSeeder::class,
            AverageLevelsSeeder::class,
        ]);
    }
}
