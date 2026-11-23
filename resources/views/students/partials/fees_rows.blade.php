@foreach($rows as $entry)
    <tr class="{{ $entry->is_paid ? 'hover:bg-emerald-50/30' : 'bg-rose-50/40 hover:bg-rose-50/60' }} transition-all">
        <td class="px-6 py-5 font-medium {{ $entry->is_paid ? 'text-gray-800' : 'text-rose-800' }}">
            {{ $entry->month->locale('ar')->isoFormat('MMMM YYYY') }}
        </td>
        <td class="px-6 py-5 font-black {{ $entry->is_paid ? 'text-gray-800' : 'text-rose-600' }}">
            {{ $entry->subscription ? number_format($entry->subscription->amount, 2) : '—' }} ج.م
        </td>
        <td class="px-6 py-5 text-gray-500 text-sm">{{ $entry->subscription?->paid_at?->format('Y/m/d') ?? '—' }}</td>
        <td class="px-6 py-5">
            @if($entry->subscription)
                <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-xs font-bold">
                    {{ ['cash' => 'نقدي', 'transfer' => 'تحويل بنكي'][$entry->subscription->payment_method] ?? $entry->subscription->payment_method }}
                </span>
            @else
                <span class="text-gray-300">—</span>
            @endif
        </td>
        <td class="px-6 py-5">
            @if($entry->is_paid)
                <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold">مدفوع</span>
            @else
                <span class="px-3 py-1 bg-rose-100 text-rose-700 rounded-lg text-xs font-bold">غير مدفوع</span>
            @endif
        </td>
    </tr>
@endforeach
