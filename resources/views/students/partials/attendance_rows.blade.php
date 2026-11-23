@php
    $statusClasses = ['present' => 'bg-emerald-100 text-emerald-700', 'absent' => 'bg-rose-100 text-rose-700', 'late' => 'bg-amber-100 text-amber-700', 'excused' => 'bg-blue-100 text-blue-700'];
    $statusLabels = ['present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر', 'excused' => 'بعذر'];
@endphp
@foreach($rows as $att)
    <tr class="hover:bg-emerald-50/30 transition-all">
        <td class="px-6 py-5 font-medium text-gray-800">
            {{ $att->date->locale('ar')->isoFormat('dddd، D MMMM YYYY') }}
        </td>
        <td class="px-6 py-5">
            <span class="px-3 py-1 rounded-lg text-xs font-bold {{ $statusClasses[$att->status] ?? 'bg-gray-100 text-gray-700' }}">
                {{ $statusLabels[$att->status] ?? $att->status }}
            </span>
        </td>
        <td class="px-6 py-5 text-gray-500 text-sm">{{ $att->notes ?? '—' }}</td>
    </tr>
@endforeach
