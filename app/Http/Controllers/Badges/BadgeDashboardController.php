<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Models\Badges\BadgeCollectionRound;
use App\Models\Badges\BadgeDistribution;
use App\Models\Badges\BadgeEntitlement;
use App\Models\Badges\BadgeLog;
use App\Models\Badges\BadgeShortage;
use App\Models\Badges\BadgeTeacherHandover;
use App\Models\Badges\BadgeType;
use App\Services\Badges\BadgeBalanceService;
use Illuminate\Http\Request;

class BadgeDashboardController extends Controller
{
    public function index(Request $request, BadgeBalanceService $balances)
    {
        $user = $request->user();
        $isTeacherOnly = $user->hasRole('teacher') && !$user->hasRole(['admin', 'general_manager', 'manager', 'supervisor']);

        $adminBalances = $balances->adminBalances();

        $distributionsQuery = BadgeDistribution::active();
        $shortagesQuery = BadgeShortage::query();
        if ($isTeacherOnly) {
            $distributionsQuery->where('teacher_id', $user->id);
            $shortagesQuery->where('teacher_id', $user->id);
        }

        $stats = [
            'types_count'        => BadgeType::count(),
            'admin_stock_total'  => array_sum($adminBalances),
            'handed_over_total'  => (int) BadgeTeacherHandover::active()->sum('quantity'),
            'distributed_total'  => (int) (clone $distributionsQuery)->sum('quantity'),
            'shortage_open_count' => (int) (clone $shortagesQuery)->open()->count(),
            'shortage_open_qty'  => (int) (clone $shortagesQuery)->open()->sum('shortage_quantity'),
            'entitlements_open'  => BadgeEntitlement::whereIn('status', ['pending', 'partial'])->count(),
            'rounds_completed'   => BadgeCollectionRound::where('status', 'completed')->count(),
            'collected_total'    => $balances->totalCollected(),
            'missing_total'      => $balances->totalMissing(),
            'damaged_total'      => $balances->totalDamaged(),
        ];

        $types = BadgeType::orderBy('name')->get();
        $latestLogs = $user->can('badges.logs.view')
            ? BadgeLog::with('user')->latest('id')->limit(10)->get()
            : collect();

        $myBalances = $user->teacher ? $balances->teacherBalances($user->id) : [];

        return view('badges.dashboard', compact('stats', 'types', 'adminBalances', 'latestLogs', 'myBalances', 'isTeacherOnly'));
    }
}
