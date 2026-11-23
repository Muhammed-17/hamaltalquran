<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ترحيل بيانات فقط (لا تغيير في البنية ولا أي معرّفات جديدة):
 * فتح سجل متبقٍّ (نواقص) لكل استحقاق قائم غير ملغي له كمية غير مسلّمة
 * ولا سجل له بعد — تطبيقًا لقاعدة أن النواقص تعرض كل ما لم يصل إلى
 * الطالب بغض النظر عن رصيد الموزّع. آمنة على البيانات الحقيقية:
 * إدراج فقط في badge_shortages، ولا حذف ولا تعديل لأي صف قائم.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('badge_entitlements')
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->chunkById(200, function ($entitlements) {
                foreach ($entitlements as $e) {
                    $exists = DB::table('badge_shortages')
                        ->where('entitlement_id', $e->id)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    $delivered = (int) DB::table('badge_distributions')
                        ->where('entitlement_id', $e->id)
                        ->where('status', 'active')
                        ->sum('quantity');

                    $redeemed = (int) DB::table('badge_redemption_items')
                        ->join('badge_redemptions', 'badge_redemptions.id', '=', 'badge_redemption_items.redemption_id')
                        ->where('badge_redemption_items.entitlement_id', $e->id)
                        ->where('badge_redemptions.status', 'completed')
                        ->sum('badge_redemption_items.shortage_quantity');

                    $remaining = max(0, (int) $e->quantity - $delivered - $redeemed);
                    if ($remaining <= 0) {
                        continue;
                    }

                    DB::table('badge_shortages')->insert([
                        'entitlement_id'     => $e->id,
                        'student_id'         => $e->student_id,
                        'teacher_id'         => $e->teacher_id,
                        'badge_type_id'      => $e->badge_type_id,
                        'required_quantity'  => (int) $e->quantity,
                        'delivered_quantity' => $delivered,
                        'shortage_quantity'  => $remaining,
                        'status'             => ($delivered > 0 || $redeemed > 0) ? 'partial' : 'pending',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // لا عكس: السجلات المفتوحة بيانات صحيحة بذاتها، وحذفها عند التراجع
        // قد يمحو سجلات شرعية — تُترك كما هي عمدًا.
    }
};
