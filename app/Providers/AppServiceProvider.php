<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;

class AppServiceProvider extends ServiceProvider
{

    protected array $policies = [
        \App\Models\Student::class => \App\Policies\StudentPolicy::class,
        \App\Models\Attendance::class => \App\Policies\AttendancePolicy::class,
        \App\Models\Subscription::class => \App\Policies\SubscriptionPolicy::class,
        \App\Models\Circle::class => \App\Policies\CirclePolicy::class,
        \App\Models\SubscriptionPrice::class => \App\Policies\SubscriptionPricePolicy::class,
        \App\Models\GroupSessionPlan::class => \App\Policies\GroupSessionPlanPolicy::class,
        \App\Models\Teacher::class => \App\Policies\TeacherPolicy::class,
        \App\Models\StudentWeeklyFollowup::class => \App\Policies\StudentWeeklyFollowupPolicy::class,
        \App\Models\Recommendation::class => \App\Policies\RecommendationPolicy::class,
        \App\Models\CollectionRound::class => \App\Policies\CollectionRoundPolicy::class,
        \App\Models\Competition::class => \App\Policies\CompetitionPolicy::class,
        \App\Models\CompetitionParticipant::class => \App\Policies\CompetitionParticipantPolicy::class,

    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        
        \Carbon\Carbon::setLocale('ar');


        // 1. تسجيل الـ Policies الخاصة بالـ Gates والصلاحيات
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // 2. حل مشكلة الـ CSS والتنسيقات المكسورة مع ngrok
        if (str_contains(request()->headers->get('host'), 'ngrok-free.dev')) {

            // إجبار Laravel على توليد كل الروابط ببروتوكول HTTPS الآمن
            URL::forceScheme('https');

            // إجبار أداة Vite على إرسال الـ Headers المناسبة عبر البروكسي
            Vite::useScriptTagAttributes([
                'crossorigin' => 'anonymous'
            ]);

            // تعيين مسار الـ Assets ليتطابق مع رابط ngrok المباشر للزائر
            config(['app.asset_url' => 'https://' . request()->headers->get('host')]);
        }
        $this->app->booted(function () {
            $host = request()->headers->get('host', '');

            if (str_contains($host, 'ngrok-free.dev')) {
                URL::forceScheme('https');
                Vite::useScriptTagAttributes(['crossorigin' => 'anonymous']);
                config(['app.asset_url' => 'https://' . $host]);
            }
        });

        // 3. تسجيل آخر وقت دخول للمستخدم (last_login_at)
        Event::listen(Login::class, function ($event) {
            $event->user->forceFill(['last_login_at' => now()])->save();
        });

    }
}
