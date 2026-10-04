<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // ✅ SQLite: إعادة إنشاء الجدول (Table Recreation)
            // لأن SQLite لا يدعم DROP/ADD PRIMARY KEY

            // 1. إنشاء جدول مؤقت بالهيكل الجديد
            DB::statement("
                CREATE TABLE circle_teacher_new (
                    circle_id INTEGER NOT NULL,
                    teacher_id INTEGER NOT NULL,
                    role VARCHAR(255) NOT NULL DEFAULT 'teacher',
                    created_at DATETIME,
                    updated_at DATETIME,
                    PRIMARY KEY (circle_id, teacher_id, role),
                    FOREIGN KEY (circle_id) REFERENCES circles(id) ON DELETE CASCADE,
                    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
                )
            ");

            // 2. نسخ البيانات مع إضافة role الافتراضي
            DB::statement("
                INSERT INTO circle_teacher_new (circle_id, teacher_id, role, created_at, updated_at)
                SELECT circle_id, teacher_id, 'teacher', created_at, updated_at
                FROM circle_teacher
            ");

            // 3. حذف الجدول القديم
            DB::statement('DROP TABLE circle_teacher');

            // 4. إعادة تسمية الجدول الجديد
            DB::statement('ALTER TABLE circle_teacher_new RENAME TO circle_teacher');

            // 5. إنشاء فهرس على teacher_id
            DB::statement('CREATE INDEX circle_teacher_teacher_id_idx ON circle_teacher (teacher_id)');
        } elseif ($driver === 'pgsql') {
            // ✅ PostgreSQL (Supabase)

            // التأكد من عدم وجود قيم NULL في role (لأنه سيصبح جزءًا من PK)
            DB::statement("UPDATE circle_teacher SET role = 'teacher' WHERE role IS NULL");

            // حذف PRIMARY KEY القديم (الاسم الافتراضي في PostgreSQL: <table>_pkey)
            DB::statement('ALTER TABLE circle_teacher DROP CONSTRAINT IF EXISTS circle_teacher_pkey');

            // إضافة PRIMARY KEY الجديد
            DB::statement('ALTER TABLE circle_teacher ADD PRIMARY KEY (circle_id, teacher_id, role)');

            // فهرس على teacher_id (الـ PK الجديد يغطي circle_id بالفعل)
            DB::statement('CREATE INDEX IF NOT EXISTS circle_teacher_teacher_id_idx ON circle_teacher (teacher_id)');
        } else {
            // ✅ MySQL/MariaDB: ALTER TABLE العادي

            // إضافة فهارس مؤقتة
            DB::statement('ALTER TABLE circle_teacher ADD INDEX circle_teacher_circle_id_idx (circle_id)');
            DB::statement('ALTER TABLE circle_teacher ADD INDEX circle_teacher_teacher_id_idx (teacher_id)');

            // حذف PRIMARY KEY القديم
            DB::statement('ALTER TABLE circle_teacher DROP PRIMARY KEY');

            // إضافة PRIMARY KEY الجديد
            DB::statement('ALTER TABLE circle_teacher ADD PRIMARY KEY (circle_id, teacher_id, role)');

            // حذف فهرس circle_id المؤقت (الـ PK الجديد يغطيه)
            DB::statement('ALTER TABLE circle_teacher DROP INDEX circle_teacher_circle_id_idx');

            // الاحتفاظ بفهرس teacher_id
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // ✅ SQLite: إعادة إنشاء الجدول بالهيكل القديم

            DB::statement("
                CREATE TABLE circle_teacher_new (
                    circle_id INTEGER NOT NULL,
                    teacher_id INTEGER NOT NULL,
                    role VARCHAR(255) NOT NULL DEFAULT 'teacher',
                    created_at DATETIME,
                    updated_at DATETIME,
                    PRIMARY KEY (circle_id, teacher_id),
                    FOREIGN KEY (circle_id) REFERENCES circles(id) ON DELETE CASCADE,
                    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
                )
            ");

            DB::statement("
                INSERT INTO circle_teacher_new (circle_id, teacher_id, role, created_at, updated_at)
                SELECT circle_id, teacher_id, role, created_at, updated_at
                FROM circle_teacher
            ");

            DB::statement('DROP TABLE circle_teacher');
            DB::statement('ALTER TABLE circle_teacher_new RENAME TO circle_teacher');

            // إنشاء فهرس على teacher_id
            DB::statement('CREATE INDEX circle_teacher_teacher_id_idx ON circle_teacher (teacher_id)');
        } elseif ($driver === 'pgsql') {
            // ✅ PostgreSQL (Supabase)
            DB::statement('ALTER TABLE circle_teacher DROP CONSTRAINT IF EXISTS circle_teacher_pkey');
            DB::statement('ALTER TABLE circle_teacher ADD PRIMARY KEY (circle_id, teacher_id)');
        } else {
            // ✅ MySQL/MariaDB
            DB::statement('ALTER TABLE circle_teacher ADD INDEX circle_teacher_circle_id_idx (circle_id)');
            DB::statement('ALTER TABLE circle_teacher DROP PRIMARY KEY');
            DB::statement('ALTER TABLE circle_teacher ADD PRIMARY KEY (circle_id, teacher_id)');
            DB::statement('ALTER TABLE circle_teacher DROP INDEX circle_teacher_circle_id_idx');
        }
    }
};
