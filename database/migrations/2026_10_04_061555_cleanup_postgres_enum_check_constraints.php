<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * هذه الأعمدة تحوّلت إلى نص عادي (varchar) في MySQL بميجريشنات change_*،
     * لكن في PostgreSQL بقيت قيود CHECK القديمة الناتجة عن enum.
     * نحذفها حتى يتطابق سلوك PostgreSQL مع MySQL.
     */
    private array $tables = [
        'students',
        'circles',
        'subscriptions',
        'subscription_prices',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            $constraints = DB::select(
                "SELECT conname FROM pg_constraint
                 WHERE contype = 'c'
                   AND conrelid = ?::regclass",
                ["public.{$table}"]
            );

            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS \"{$constraint->conname}\"");
            }
        }
    }

    public function down(): void
    {
        // لا يمكن استرجاع القيود القديمة لأن قيمها لم تعد صالحة.
    }
};