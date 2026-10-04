<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ توحيد أي قيم غير صالحة قبل تغيير الـ enum
        DB::table('attendances')
            ->whereNotIn('status', ['present', 'absent', 'late', 'excused'])
            ->update(['status' => 'present']);

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // ✅ PostgreSQL: الـ enum يُمثَّل بـ VARCHAR + CHECK constraint
            // (بنفس الطريقة التي يستخدمها Laravel مع pgsql)
            DB::statement('ALTER TABLE attendances DROP CONSTRAINT IF EXISTS attendances_status_check');
            DB::statement('ALTER TABLE attendances ALTER COLUMN status TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE attendances ALTER COLUMN status SET NOT NULL');
            DB::statement("
                ALTER TABLE attendances
                ADD CONSTRAINT attendances_status_check
                CHECK (status::text IN ('present', 'absent', 'late', 'excused'))
            ");
        } elseif ($driver === 'sqlite') {
            // ✅ SQLite: لا يدعم تعديل العمود، والبيانات تم توحيدها بالأعلى
            // لذلك نتجاوز الخطوة (العمود يبقى نصيًا).
        } else {
            // ✅ MySQL/MariaDB
            DB::statement("ALTER TABLE attendances MODIFY COLUMN status ENUM('present', 'absent', 'late', 'excused') NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE attendances DROP CONSTRAINT IF EXISTS attendances_status_check');
            DB::statement('ALTER TABLE attendances ALTER COLUMN status TYPE VARCHAR(255)');
        } elseif ($driver === 'sqlite') {
            // لا شيء
        } else {
            DB::statement('ALTER TABLE attendances MODIFY COLUMN status VARCHAR(255) NOT NULL');
        }
    }
};
