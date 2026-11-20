<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use App\Http\Middleware\CheckUserStatus;
use App\Http\Middleware\EnsureNotGuardian;
use \App\Http\Middleware\UpdateLastSeen;
use \App\Http\Middleware\PreventBackHistoryCache;
use Illuminate\Support\Facades\Auth;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '127.0.0.1',
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->alias([
            'role'               => RoleMiddleware::class,
            'permission'         => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'not.guardian'       => EnsureNotGuardian::class,
        ]);
        $middleware->web(append: [
            CheckUserStatus::class,
            UpdateLastSeen::class,
            PreventBackHistoryCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // ================================================================
        // ✅ Helper — redirect لآخر صفحة بدون أي رسالة
        // ================================================================
        $handle403 = function ($request) {

            // ✅ AJAX — بدون رسالة
            if ($request->expectsJson()) {
                return response()->json([], 403);
            }

            // ✅ مستخدم مسجل بلا دور معروف للنظام (بلا دور أصلًا، أو دور غير
            // معروف، أو حُذف دوره أثناء الجلسة): لا وجهة آمنة له — خروج نظيف
            $authUser = Auth::user();
            if ($authUser && !$authUser->hasAnyRole(['admin', 'general_manager', 'manager', 'supervisor', 'teacher', 'guardian', 'examiner'])) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login');
            }

            // ✅ كشف سلسلة 403 متتالية: لو حدث 403 آخر خلال ثوانٍ من سابقه
            // فالصفحة السابقة نفسها ممنوعة على هذا الحساب (لا وجهة آمنة له) —
            // لا نرتد إليها حتى لا ندخل في تبادل لا نهائي بين صفحتين ممنوعتين
            $nowTs = now()->timestamp;
            $inForbiddenChain = ($nowTs - (int) $request->session()->get('last_403_at', 0)) <= 5;
            $request->session()->put('last_403_at', $nowTs);

            $previous = url()->previous();
            $current  = url()->current();

            // ✅ يرجع لآخر صفحة لو مختلفة عن الحالية ولسنا في سلسلة ممنوعات
            if (!$inForbiddenChain && $previous && $previous !== $current) {
                return redirect()->to($previous);
            }

            // ✅ Fallback حسب الدور — بدون أي رسالة
            $user = Auth::user();

            $fallback = match (true) {
                !$user                            => route('login'),
                $user->hasRole('guardian')        => route('guardian.dashboard.own'),
                $user->hasRole('admin')           => route('dashboard'),
                $user->hasRole('general_manager') => route('students.index'),
                $user->hasRole('manager')         => route('students.index'),
                $user->hasRole('supervisor')      => route('students.index'),
                $user->hasRole('teacher')         => route('students.index'),
                $user->hasRole('examiner')        => route('examiner.dashboard'),
                // لا يصل هنا مستخدم مسجل بدور غير معروف (يُخرَج أعلاه) —
                // أبقيناها على الملف الشخصي احتياطًا بدل route('login')
                // التي تعيد المستخدم المسجل إلى الداخل فتتولد حلقة
                default                           => route('profile.edit'),
            };

            // ✅ منع redirect loop: الوجهة الاحتياطية هي نفس الصفحة الممنوعة —
            // خروج آمن للمستخدم المسجل بدل إرساله لصفحة الدخول وهو مسجل
            if (url()->current() === $fallback) {
                if ($user) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return redirect()->route('login');
            }

            return redirect()->to($fallback);
        };

        // ================================================================
        // Spatie UnauthorizedException
        // ================================================================
        $exceptions->render(
            function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) use ($handle403) {
                return $handle403($request);
            }
        );

        // ================================================================
        // abort(403) من Controller أو Policy
        // ================================================================
        $exceptions->render(
            function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) use ($handle403) {
                return $handle403($request);
            }
        );

        // ================================================================
        // abort(403) المباشر داخل الكنترولرز (abort / abort_if):
        // هذه تنتج HttpException عامة بحالة 403 لا AccessDeniedHttpException،
        // فبدون هذا المعالج تظهر صفحة 403 خام ولا يصلها منطق التحويل أصلًا
        // ================================================================
        $exceptions->render(
            function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) use ($handle403) {
                if ($e->getStatusCode() === 403) {
                    return $handle403($request);
                }
            }
        );

        // ================================================================
        // ✅ 404 — صفحة/route غير موجودة (بما فيها Model Not Found)
        // صفحة 404 حقيقية بحالة 404 بدل التحويل الصامت للرئيسية،
        // حتى لا تُخفى الروابط الخاطئة ولا تُفهرس كصفحة رئيسية.
        // معالج 403 أعلاه يبقى كما هو (تحويل بدون رسالة) — الفرق مقصود.
        // ================================================================
        $exceptions->render(
            function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'الصفحة غير موجودة'], 404);
                }

                return response()->view('errors.404', [], 404);
            }
        );

        // ================================================================
        // ✅ 419 — انتهاء صلاحية الجلسة
        // ================================================================
        $exceptions->render(
            function (\Illuminate\Session\TokenMismatchException $e, $request) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة',
                    ], 419);
                }

                return redirect()->route('login');
            }
        );
    })->create();
