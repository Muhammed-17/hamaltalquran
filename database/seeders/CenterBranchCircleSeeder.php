<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Branch;
use App\Models\Circle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CenterBranchCircleSeeder extends Seeder
{
    /**
     * ملاحظات قبل التشغيل:
     * 1) ضع centers.json / branches.json / circles.json في database/seeders/data/
     * 2) شغّله الأول قبل أي Seeder تاني بيستخدم مراكز/فروع/حلقات:
     *    php artisan db:seed --class=CenterBranchCircleSeeder
     * 3) بيعتمد على firstOrCreate بالاسم، فلو المركز/الفرع/الحلقة
     *    موجودين بالفعل (من تشغيل سابق) مش هيتكرروا.
     */
    public function run(): void
    {
        $this->seedCenters();
        $this->seedBranches();
        $this->seedCircles();
    }

    private function seedCenters(): void
    {
        $path = database_path('seeders/data/centers.json');
        if (!file_exists($path)) {
            $this->command->error("ملف البيانات غير موجود: {$path}");
            return;
        }

        $rows = json_decode(file_get_contents($path), true);

        foreach ($rows as $row) {
            Center::firstOrCreate(
                ['name' => $row['name']],
                ['established_at' => $row['established_at'] ?? null]
            );
        }

        $this->command->info('تم إنشاء المراكز: ' . count($rows));
    }

    private function seedBranches(): void
    {
        $path = database_path('seeders/data/branches.json');
        if (!file_exists($path)) {
            $this->command->error("ملف البيانات غير موجود: {$path}");
            return;
        }

        $rows = json_decode(file_get_contents($path), true);

        foreach ($rows as $row) {
            $center = Center::where('name', $row['center_name'])->first();

            if (!$center) {
                $this->command->warn("مركز غير موجود، تم تخطي الفرع: {$row['name']} ({$row['center_name']})");
                continue;
            }

            Branch::firstOrCreate(
                ['name' => $row['name'], 'center_id' => $center->id],
                [
                    'address' => $row['address'] ?? null,
                    'established_at' => $row['established_at'] ?? null,
                ]
            );
        }

        $this->command->info('تم إنشاء الفروع: ' . count($rows));
    }

    private function seedCircles(): void
    {
        $path = database_path('seeders/data/circles.json');
        if (!file_exists($path)) {
            $this->command->error("ملف البيانات غير موجود: {$path}");
            return;
        }

        $rows = json_decode(file_get_contents($path), true);

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $center = Center::where('name', $row['center_name'])->first();
                $branch = $center
                    ? Branch::where('name', $row['branch_name'])->where('center_id', $center->id)->first()
                    : null;

                if (!$branch) {
                    $this->command->warn("فرع غير موجود، تم تخطي الحلقة: {$row['name']} ({$row['branch_name']} / {$row['center_name']})");
                    continue;
                }

                Circle::firstOrCreate(
                    ['name' => $row['name'], 'branch_id' => $branch->id],
                    [
                        'type' => $row['type'],
                        'level' => $row['level'],
                        'url' => $row['url'] ?? '',
                    ]
                );
            }
        });

        $this->command->info('تم إنشاء الحلقات: ' . count($rows));
    }
}
