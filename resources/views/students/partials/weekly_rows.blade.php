@foreach($rows as $followup)
                                <tr class="hover:bg-emerald-50/30 transition-all">
                                    <td class="px-6 py-5 font-medium text-gray-800">
                                        {{ $followup->week_start?->format('Y-m-d') }} — {{ $followup->week_end?->format('Y-m-d') }}
                                    </td>
                                    <td class="px-6 py-5">
                                        @if($followup->plan_type === 'group')
                                        <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-bold">جماعي</span>
                                        @else
                                        <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-bold">فردي</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-5 text-gray-600">{{ $followup->teacher?->user?->name ?? '-' }}</td>
                                    <td class="px-6 py-5 text-gray-600 text-sm">
                                        {{ $followup->newMemorizations?->toSurah?->name_arabic ?? '—' }}
                                    </td>
                                    <td class="px-6 py-5 text-gray-600 text-sm">
                                        {{ $followup->revisions?->toSurah?->name_arabic ?? '—' }}
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-1.5">
                                            @can('view', $followup)
                                            <a href="{{ route('student-weekly-followups.show', $followup) }}" class="w-7 h-7 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center"><i class="fas fa-eye text-xs"></i></a>
                                            @endcan

                                            @can('update', $followup)
                                            <a href="{{ route('student-weekly-followups.edit', $followup) }}" class="w-7 h-7 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center"><i class="fas fa-edit text-xs"></i></a>
                                            @endcan

                                            @can('delete', $followup)
                                            @if($followup->plan_type === 'group')
                                            <form action="{{ route('student-weekly-followups.destroy-group', $followup->batch_id) }}" method="POST" class="inline" id="delete-followup-{{ $followup->id }}">
                                                @csrf @method('DELETE')
                                                <button type="button"
                                                    onclick="confirmDelete(event, { name: '{{ $followup->week_start?->format('Y-m-d') }}', type: 'المتابعة الأسبوعية', form: document.getElementById('delete-followup-{{ $followup->id }}') })"
                                                    class="w-7 h-7 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            </form>
                                            @else
                                            <form action="{{ route('student-weekly-followups.destroy', $followup) }}" method="POST" class="inline" id="delete-followup-{{ $followup->id }}">
                                                @csrf @method('DELETE')
                                                <button type="button"
                                                    onclick="confirmDelete(event, { name: '{{ $followup->week_start?->format('Y-m-d') }}', type: 'المتابعة الأسبوعية', form: document.getElementById('delete-followup-{{ $followup->id }}') })"
                                                    class="w-7 h-7 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                
@endforeach
