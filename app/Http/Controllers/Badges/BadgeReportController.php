<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Models\Badges\BadgeCollectionItem;
use App\Models\Badges\BadgeDistribution;
use App\Models\Badges\BadgeShortage;
use App\Models\Badges\BadgeTeacherHandover;
use App\Models\Badges\BadgeType;
use App\Models\User;
use App\Services\Badges\BadgeBalanceService;
use Illuminate\Http\Request;

class BadgeReportController extends Controller
{
    public function index(Request $request, BadgeBalanceService $balances)
    {
        $types = BadgeType::orderBy('name')->get();
        $adminBalances = $balances->adminBalances();

        // أرصدة المعلمين لكل نوع
        $teacherIds = collect()
            ->merge(BadgeTeacherHandover::pluck('teacher_id'))
            ->merge(BadgeDistribution::pluck('teacher_id'))
            ->unique();
        $teachers = User::whereIn('id', $teacherIds)->orderBy('name')->get(['id', 'name']);
        $teacherBalances = [];
        foreach ($teachers as $teacher) {
            $teacherBalances[$teacher->id] = $balances->teacherBalances($teacher->id);
        }

        // ملخص التوزيع والتحصيل لكل نوع
        $distributedPerType = BadgeDistribution::active()
            ->selectRaw('badge_type_id, SUM(quantity) as total')
            ->groupBy('badge_type_id')->pluck('total', 'badge_type_id');

        $collectedPerType = BadgeCollectionItem::whereHas('round', fn ($q) => $q->where('status', 'completed'))
            ->selectRaw('badge_type_id, SUM(collected_quantity) as total')
            ->groupBy('badge_type_id')->pluck('total', 'badge_type_id');

        $missingPerType = BadgeCollectionItem::where('status', 'missing')
            ->whereHas('round', fn ($q) => $q->where('status', 'completed'))
            ->selectRaw('badge_type_id, SUM(distributed_quantity - collected_quantity) as total')
            ->groupBy('badge_type_id')->pluck('total', 'badge_type_id');

        $openShortages = BadgeShortage::open()
            ->with(['student', 'teacher', 'badgeType', 'entitlement.circle'])
            ->latest('id')->limit(50)->get();

        $missingItems = BadgeCollectionItem::where('status', 'missing')
            ->whereHas('round', fn ($q) => $q->where('status', 'completed'))
            ->with(['student', 'badgeType', 'round.circle'])
            ->latest('id')->limit(50)->get();

        return view('badges.reports.index', compact(
            'types', 'adminBalances', 'teachers', 'teacherBalances',
            'distributedPerType', 'collectedPerType', 'missingPerType',
            'openShortages', 'missingItems'
        ));
    }
}
