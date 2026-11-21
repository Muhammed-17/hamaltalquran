<?php

namespace App\Http\Controllers\Badges;

use App\Http\Controllers\Controller;
use App\Models\Badges\BadgeLog;
use Illuminate\Http\Request;

class BadgeLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = BadgeLog::with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', "%{$request->action}%"))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->entity_type))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('badges.logs.index', compact('logs'));
    }
}
