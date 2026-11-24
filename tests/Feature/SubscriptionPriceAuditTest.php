<?php

use App\Models\SubscriptionPrice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * فحص صفحة أسعار الاشتراكات:
 * الصفحة مقفولة بدور الأدمن وحده (حُذفت صلاحيتا view/manage subscription prices في تقرير 211)،
 * والتحقق كان يقبل أي نص لمستوى الحلقة والمرحلة فينشئ أسعارًا ميتة لا تطابق أي طالب.
 */
function priceAuditUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);
    $u = User::create([
        'name' => 'مستخدم ' . $role, 'email' => $role . '_' . uniqid() . '@audit.test',
        'password' => Hash::make('password'), 'email_verified_at' => now(),
    ]);
    $u->assignRole($role);

    return $u;
}

test('roles without the price permissions cannot view, store, or delete prices', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $manager = priceAuditUser('manager');
    $price = SubscriptionPrice::create(['circle_level' => 'build', 'education_stage' => 'ابتدائي', 'amount' => 100]);

    $this->actingAs($manager)->get(route('subscription-prices.index'))->assertRedirect();
    $this->actingAs($manager)->post(route('subscription-prices.store'), [
        'circle_level' => 'mastery', 'education_stage' => 'ثانوي', 'amount' => 300,
    ])->assertRedirect();
    $this->actingAs($manager)->delete(route('subscription-prices.destroy', $price))->assertRedirect();

    expect(SubscriptionPrice::count())->toBe(1)
        ->and(SubscriptionPrice::find($price->id))->not->toBeNull();
});

test('bogus circle levels and education stages are rejected, valid combos upsert', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $admin = priceAuditUser('admin');

    $this->actingAs($admin)->post(route('subscription-prices.store'), [
        'circle_level' => 'xyz', 'education_stage' => 'ابتدائي', 'amount' => 100,
    ])->assertSessionHasErrors('circle_level');

    $this->actingAs($admin)->post(route('subscription-prices.store'), [
        'circle_level' => 'build', 'education_stage' => 'ابتدائيي', 'amount' => 100,
    ])->assertSessionHasErrors('education_stage');

    $this->actingAs($admin)->post(route('subscription-prices.store'), [
        'circle_level' => 'build', 'education_stage' => 'ابتدائي', 'amount' => -5,
    ])->assertSessionHasErrors('amount');

    expect(SubscriptionPrice::count())->toBe(0);

    // حفظ ثم تحديث نفس التركيبة: صف واحد بالمبلغ الجديد
    $this->actingAs($admin)->post(route('subscription-prices.store'), [
        'circle_level' => 'build', 'education_stage' => 'ابتدائي', 'amount' => 150,
    ])->assertRedirect(route('subscription-prices.index'));
    $this->actingAs($admin)->post(route('subscription-prices.store'), [
        'circle_level' => 'build', 'education_stage' => 'ابتدائي', 'amount' => 200,
    ])->assertRedirect(route('subscription-prices.index'));

    expect(SubscriptionPrice::count())->toBe(1)
        ->and((float) SubscriptionPrice::first()->amount)->toBe(200.0);

    // الحذف يعمل للأدمن
    $this->actingAs($admin)->delete(route('subscription-prices.destroy', SubscriptionPrice::first()))
        ->assertRedirect(route('subscription-prices.index'));
    expect(SubscriptionPrice::count())->toBe(0);
});
