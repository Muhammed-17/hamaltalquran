@foreach($rows as $d)
    <tr class="hover:bg-emerald-50/30 transition-all">
        <td class="px-6 py-5 text-gray-500 text-sm">{{ \Carbon\Carbon::parse($d->distribution_date)->format('Y/m/d') }}</td>
        <td class="px-6 py-5 font-bold text-gray-800">{{ $d->badgeType?->name ?? '—' }}</td>
        <td class="px-6 py-5 font-black text-gray-800">{{ $d->quantity }}</td>
    </tr>
@endforeach
