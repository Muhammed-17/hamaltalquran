<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionTransfer;
use App\Models\User;
use App\Services\UserAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriptionTransferController extends Controller
{
    public function __construct(protected UserAccessService $access) {}

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()->can('transfer subscriptions'), 403, 'ليس لديك صلاحية الوصول لهذه الصفحة');
    }

    // ─────────────────────────────────────────
    public function index()
    {
        $this->authorizeAccess();

        $user    = Auth::user();
        $circles = $this->access->accessibleCircles($user)->get(['id', 'name']);

        // ✅ نفس منطق بناء قائمة المحصِّلين المستخدَم في SubscriptionController
        $collectedByUsers = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['teacher', 'supervisor', 'manager', 'general_manager']);
        })->orderBy('name')->get(['id', 'name']);

        // ✅ قائمة "المشرف على هذه العملية" — تظهر فقط للأدمن/المدير العام
        $performers = collect();
        if ($user->hasAnyRole(['admin', 'general_manager'])) {
            $performers = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['supervisor', 'manager', 'general_manager']);
            })->orderBy('name')->get(['id', 'name']);
        }

        return view('subscriptions.transfer', [
            'circles'          => $circles,
            'collectedByUsers' => $collectedByUsers,
            'performers'       => $performers,
        ]);
    }

    // ─────────────────────────────────────────
    // ✅ بحث الاشتراكات المطابقة لحلقة + شهر + المحصِّل الحالي
    public function search(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'circle_id'    => 'required|integer|exists:circles,id',
            'month'        => 'required|date_format:Y-m',
            'collected_by' => 'required|integer|exists:users,id',
        ]);

        $monthDate = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()->format('Y-m-d');

        $subscriptions = Subscription::query()
            ->with(['student:id,name', 'collectionRoundItem.collectionRound']) // ✅ جديد
            ->where('circle_id', $validated['circle_id'])
            ->where('month', $monthDate)
            ->where(function ($q) use ($validated) {
                $q->where(fn($qq) => $qq->where('status', 'مدفوع')->where('collected_by', $validated['collected_by']))
                    ->orWhere('status', 'معفي');
            })
            ->orderByRaw("FIELD(status, 'مدفوع', 'معفي')")
            ->orderBy('id')
            ->get()
            ->map(function ($s) {
                // ✅ نفس شرط الحماية المستخدم في SubscriptionController@update/destroy
                $isLocked = $s->collectionRoundItem && $s->collectionRoundItem->collectionRound?->status === 'confirmed';

                return [
                    'id'           => $s->id,
                    'student_name' => $s->student?->name ?? '—',
                    'amount'       => (float) $s->amount,
                    'status'       => $s->status,
                    'paid_at'      => optional($s->paid_at)->format('Y-m-d'),
                    'transferable' => in_array($s->status, ['مدفوع', 'معفي']) && !$isLocked, // ✅ الممنوع محمي يُستبعد
                    'locked'       => $isLocked, // ✅ جديد — للواجهة، عشان تعرض سبب المنع
                ];
            });

        return response()->json(['subscriptions' => $subscriptions]);
    }

    // ─────────────────────────────────────────
    // ✅ تحويل الاشتراكات المحددة لمحصِّل جديد
    public function transfer(Request $request)
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'circle_id'           => 'required|integer|exists:circles,id',
            'month'               => 'required|date_format:Y-m',
            'from_user_id'        => 'required|integer|exists:users,id',
            'to_user_id'          => 'required|integer|exists:users,id|different:from_user_id',
            'performed_by'        => 'nullable|integer|exists:users,id',
            'subscription_ids'    => 'required|array|min:1',
            'subscription_ids.*'  => 'integer|exists:subscriptions,id',
            'notes'               => 'nullable|string|max:255',
        ], [
            'circle_id.required'          => 'يجب اختيار الحلقة.',
            'circle_id.exists'            => 'الحلقة المختارة غير موجودة.',
            'month.required'              => 'يجب اختيار الشهر.',
            'month.date_format'           => 'صيغة الشهر غير صحيحة.',
            'from_user_id.required'       => 'يجب اختيار المحصِّل الحالي.',
            'from_user_id.exists'         => 'المحصِّل الحالي غير موجود.',
            'to_user_id.required'         => 'يجب اختيار المحصِّل الجديد.',
            'to_user_id.exists'           => 'المحصِّل الجديد غير موجود.',
            'to_user_id.different'        => 'يجب أن يكون المحصِّل الجديد مختلفًا عن المحصِّل الحالي.',
            'performed_by.exists'         => 'المشرف المختار غير موجود.',
            'subscription_ids.required'   => 'يجب اختيار اشتراك واحد على الأقل للتحويل.',
            'subscription_ids.min'        => 'يجب اختيار اشتراك واحد على الأقل للتحويل.',
            'subscription_ids.*.exists'   => 'أحد الاشتراكات المحددة غير موجود.',
            'notes.max'                   => 'الملاحظات طويلة جدًا (الحد الأقصى 255 حرفًا).',
        ]);

        // ✅ فقط الأدمن/المدير العام يقدر يحدد شخص تاني كمنفّذ للعملية
        $performedBy = Auth::user()->hasAnyRole(['admin', 'general_manager']) && !empty($data['performed_by'])
            ? $data['performed_by']
            : Auth::id();

        DB::beginTransaction();
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth()->format('Y-m-d');

            $subscriptionsToTransfer = Subscription::whereIn('id', $data['subscription_ids'])
                ->where('circle_id', $data['circle_id'])
                ->where('month', $monthDate)
                ->where(function ($q) use ($data) {
                    $q->where(fn($qq) => $qq->where('status', 'مدفوع')->where('collected_by', $data['from_user_id']))
                        ->orWhere('status', 'معفي');
                })
                ->with('collectionRoundItem.collectionRound')
                ->get();

            // ✅ استبعاد أي اشتراك محمي بجولة تحصيل مؤكَّدة قبل التحويل
            $lockedSubscriptions = $subscriptionsToTransfer->filter(
                fn($s) => $s->collectionRoundItem && $s->collectionRoundItem->collectionRound?->status === 'confirmed'
            );

            if ($lockedSubscriptions->isNotEmpty()) {
                DB::rollBack();
                return $this->respond(
                    $request,
                    false,
                    'لا يمكن تحويل ' . $lockedSubscriptions->count() . ' اشتراك لأنها مرتبطة بجولة تحصيل مؤكَّدة. أزلها من الجولة أولاً.'
                );
            }

            $subscriptionIds = $subscriptionsToTransfer->pluck('id');

            if ($subscriptionIds->isEmpty()) {
                DB::rollBack();
                return $this->respond($request, false, 'لا توجد اشتراكات صالحة للتحويل ضمن المحددات المُرسلة.');
            }

            Subscription::whereIn('id', $subscriptionIds)->update(['collected_by' => $data['to_user_id']]);

            SubscriptionTransfer::create([
                'circle_id'            => $data['circle_id'],
                'month'                => $monthDate,
                'from_user_id'         => $data['from_user_id'],
                'to_user_id'           => $data['to_user_id'],
                'performed_by'         => $performedBy,
                'subscription_ids'     => $subscriptionIds->toArray(),
                'subscriptions_count'  => $subscriptionIds->count(),
                'notes'                => $data['notes'] ?? null,
            ]);

            DB::commit();

            return $this->respond($request, true, "تم تحويل {$subscriptionIds->count()} اشتراك بنجاح ✓");
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->respond($request, false, 'حدث خطأ أثناء التحويل: ' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────
    // ─────────────────────────────────────────
    public function history(Request $request)
    {
        $this->authorizeAccess();

        $user   = Auth::user();
        $search = $request->get('search');
        $circleId = $request->get('circle_id');
        $month  = $request->get('month');

        // ✅ نفس الحلقات المتاحة للمستخدم في صفحة التحويل
        $circles = $this->access->accessibleCircles($user)->get(['id', 'name']);

        $query = SubscriptionTransfer::with([
            'fromUser:id,name',
            'toUser:id,name',
            'performedBy:id,name',
            'circle:id,name',
        ]);

        // ✅ بحث عن اسم المعلم سواء كان "من" أو "إلى" أو "بواسطة"
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('fromUser', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('toUser', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('performedBy', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        // ✅ فلتر الحلقة
        if ($circleId) {
            $query->where('circle_id', $circleId);
        }

        // ✅ فلتر الشهر (شهر الاشتراك المُحوَّل، مش تاريخ عملية التحويل)
        if ($month) {
            $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->format('Y-m-d');
            $query->where('month', $monthDate);
        }

        $transfers = $query->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $hasActiveFilters = $request->anyFilled(['search', 'circle_id', 'month']);

        return view('subscriptions.transfer_history', compact(
            'transfers',
            'circles',
            'search',
            'circleId',
            'month',
            'hasActiveFilters'
        ));
    }

    // ─────────────────────────────────────────
    private function respond(Request $request, bool $success, string $message, int $status = 200)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : $status);
        }

        return redirect()->route('subscriptions.index')
            ->with($success ? 'success' : 'error', $message);
    }
}
