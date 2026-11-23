@foreach($rows as $row)
    <tr class="hover:bg-emerald-50/30 transition-all">
        <td class="px-6 py-5 text-gray-500 text-sm">{{ $row['round']->collection_date->format('Y/m/d') }}</td>
        <td class="px-6 py-5 font-bold text-gray-800">{{ $row['round']->teacher?->name ?? '—' }}</td>
        <td class="px-6 py-5 font-bold text-gray-800">{{ $row['type']?->name ?? '—' }}</td>
        <td class="px-6 py-5 font-black text-gray-800">{{ $row['good'] }}</td>
        <td class="px-6 py-5 font-black text-gray-800">{{ $row['damaged'] }}</td>
    </tr>
@endforeach
