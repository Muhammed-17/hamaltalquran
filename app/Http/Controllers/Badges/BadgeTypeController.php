<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\StoreBadgeTypeRequest;
use App\Http\Requests\Badges\UpdateBadgeTypeRequest;
use App\Models\Badges\BadgeType;
use App\Services\Badges\BadgeBalanceService;
use App\Services\Badges\BadgeLogService;
use Illuminate\Support\Facades\Storage;

class BadgeTypeController extends Controller
{
    public function __construct(private BadgeLogService $logger) {}

    public function index(BadgeBalanceService $balances)
    {
        $this->authorize('viewAny', BadgeType::class);

        $types = BadgeType::withCount(['handovers', 'entitlements', 'distributions'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('badges.types.index', [
            'types' => $types,
            'adminBalances' => $balances->adminBalances(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', BadgeType::class);

        return view('badges.types.create', ['type' => new BadgeType()]);
    }

    public function store(StoreBadgeTypeRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('badges', 'public');
        } else {
            unset($data['image']);
        }

        $type = BadgeType::create($data);
        $this->logger->log('type.created', $type, newValues: $type->only(['name', 'is_active']));

        return redirect()->route('badges.types.index')->with('success', 'تم إضافة نوع الوسام بنجاح.');
    }

    public function show(BadgeType $type, BadgeBalanceService $balances)
    {
        $this->authorize('view', $type);

        return view('badges.types.show', [
            'type' => $type,
            'adminBalance' => $balances->adminBalance($type->id),
        ]);
    }

    public function edit(BadgeType $type)
    {
        $this->authorize('update', $type);

        return view('badges.types.edit', compact('type'));
    }

    public function update(UpdateBadgeTypeRequest $request, BadgeType $type)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        if ($request->hasFile('image')) {
            if ($type->image) {
                Storage::disk('public')->delete($type->image);
            }
            $data['image'] = $request->file('image')->store('badges', 'public');
        } else {
            unset($data['image']);
        }

        $old = $type->only(['name', 'is_active']);
        $type->update($data);
        $this->logger->log('type.updated', $type, oldValues: $old, newValues: $type->only(['name', 'is_active']));

        return redirect()->route('badges.types.index')->with('success', 'تم تحديث نوع الوسام بنجاح.');
    }

    public function destroy(BadgeType $type)
    {
        $this->authorize('delete', $type);

        if ($type->image) {
            Storage::disk('public')->delete($type->image);
        }
        $type->delete();
        $this->logger->log('type.deleted', 'badge_types', $type->id);

        return redirect()->route('badges.types.index')->with('success', 'تم حذف نوع الوسام بنجاح.');
    }
}
