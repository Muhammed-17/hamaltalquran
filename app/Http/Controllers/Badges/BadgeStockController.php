<?php

namespace App\Http\Controllers\Badges;

use App\Enums\Badges\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\StoreBadgeReturnRequest;
use App\Http\Requests\Badges\StoreBadgeStockMovementRequest;
use App\Models\Badges\BadgeStockMovement;
use App\Models\Badges\BadgeType;
use App\Models\User;
use App\Services\Badges\BadgeAccessService;
use App\Services\Badges\BadgeBalanceService;
use App\Services\Badges\BadgeStockService;
use Illuminate\Http\Request;

class BadgeStockController extends Controller
{
    public function index(BadgeBalanceService $balances, BadgeAccessService $access)
    {
        $types = BadgeType::orderBy('name')->get();
        $recentMovements = BadgeStockMovement::with(['badgeType', 'creator'])
            ->latest('id')->limit(10)->get();

        return view('badges.stock.index', [
            'types' => $types,
            'adminBalances' => $balances->adminBalances(),
            'recentMovements' => $recentMovements,
            'teachers' => $access->selectableTeachers(auth()->user()),
            'movementTypes' => StockMovementType::cases(),
        ]);
    }

    public function history(Request $request)
    {
        $movements = BadgeStockMovement::with(['badgeType', 'creator'])
            ->when($request->filled('badge_type_id'), fn ($q) => $q->where('badge_type_id', $request->badge_type_id))
            ->when($request->filled('movement_type'), fn ($q) => $q->where('movement_type', $request->movement_type))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('badges.stock.history', [
            'movements' => $movements,
            'types' => BadgeType::orderBy('name')->get(),
            'movementTypes' => StockMovementType::cases(),
        ]);
    }

    public function storeMovement(StoreBadgeStockMovementRequest $request, BadgeStockService $stock)
    {
        $type = BadgeType::findOrFail($request->badge_type_id);

        $stock->recordMovement(
            $type,
            StockMovementType::from($request->movement_type),
            (int) $request->quantity,
            $request->user(),
            $request->notes,
        );

        return redirect()->route('badges.stock.index')->with('success', 'تم تسجيل حركة المخزون بنجاح.');
    }

    public function storeReturn(StoreBadgeReturnRequest $request, BadgeStockService $stock)
    {
        $teacher = User::findOrFail($request->teacher_id);
        $type = BadgeType::findOrFail($request->badge_type_id);

        $stock->returnFromTeacher($teacher, $type, (int) $request->quantity, $request->user(), $request->notes);

        return redirect()->route('badges.stock.index')->with('success', 'تم تسجيل الإرجاع إلى الإدارة بنجاح.');
    }
}
