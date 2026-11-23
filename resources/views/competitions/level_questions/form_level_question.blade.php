@php
$isEdit = isset($question);
$surahOptions = $surahs->map(fn($surah) => [
'value' => $surah->id,
'label' => $surah->number . ' - ' . $surah->name_arabic,
])->values()->toArray();
@endphp

<div class="max-w-3xl mx-auto space-y-6"
    x-data="{ type: '{{ old('type', $isEdit ? $question->type : 'memorization') }}' }">

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-[#0a5c36] mb-2">
            {{ $isEdit ? 'تعديل السؤال' : 'إضافة سؤال' }}
        </h1>
        <p class="text-gray-600">{{ $competition->name }}</p>
    </div>

    <form action="{{ $isEdit
            ? route('competitions.level-questions.update', [$competition, $question])
            : route('competitions.level-questions.store', $competition) }}"
        method="POST">
        @csrf
        @if($isEdit)
        @method('PUT')
        @else
        <input type="hidden" name="competition_level_id" value="{{ $selectedLevelId }}">
        @endif

        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 space-y-6">
            <div class="flex items-center gap-3 mb-2 border-b border-gray-50 pb-4">
                <div class="p-2 bg-emerald-50 rounded-xl text-[#0a5c36]">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-800">بيانات السؤال</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- المستوى --}}
                <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700">المستوى</label>
                    <x-searchable-select
                        name="competition_level_id"
                        placeholder="اختر المستوى..."
                        search-placeholder="ابحث عن مستوى..."
                        :default-value="old('competition_level_id', $isEdit ? $question->competition_level_id : ($selectedLevelId ?? ''))"
                        :options="$levels->map(fn($competitionLevel) => [
                            'value' => $competitionLevel->id,
                            'label' => $competitionLevel->level->name ?? '-',
                        ])->values()->toArray()" />
                    @error('competition_level_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- اسم السؤال --}}
                <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700">اسم السؤال</label>
                    <input type="text" name="name" value="{{ old('name', $isEdit ? $question->name : '') }}"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- النوع --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">النوع</label>
                    <select name="type" x-model="type"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all appearance-none">
                        @foreach (\App\Models\CompetitionQuestion::TYPE_LABELS as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected(old('type', $isEdit ? $question->type : 'memorization') === $typeValue)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- الدرجة --}}
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-gray-700">الدرجة</label>
                    <input type="number" step="0.5" min="0" name="score" value="{{ old('score', $isEdit ? $question->score : '') }}"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
                    @error('score') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- أجزاء سؤال الحفظ --}}
                <template x-if="type === 'memorization'">
                    <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">من سورة</label>
                            <x-searchable-select
                                name="memorization_from_surah_id"
                                placeholder="اختر السورة..."
                                search-placeholder="ابحث عن سورة..."
                                :default-value="old('memorization_from_surah_id', $isEdit ? $question->memorization_from_surah_id : '')"
                                :options="$surahOptions" />
                            @error('memorization_from_surah_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">من آية</label>
                            <input type="number" min="1" name="memorization_from_ayah" value="{{ old('memorization_from_ayah', $isEdit ? $question->memorization_from_ayah : '') }}"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
                            @error('memorization_from_ayah') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">إلى سورة</label>
                            <x-searchable-select
                                name="memorization_to_surah_id"
                                placeholder="اختر السورة..."
                                search-placeholder="ابحث عن سورة..."
                                :default-value="old('memorization_to_surah_id', $isEdit ? $question->memorization_to_surah_id : '')"
                                :options="$surahOptions" />
                            @error('memorization_to_surah_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-gray-700">إلى آية</label>
                            <input type="number" min="1" name="memorization_to_ayah" value="{{ old('memorization_to_ayah', $isEdit ? $question->memorization_to_ayah : '') }}"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">
                            @error('memorization_to_ayah') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </template>

                {{-- المحتوى --}}
                <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700">المحتوى</label>
                    <textarea name="content" rows="3"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">{{ old('content', $isEdit ? $question->content : '') }}</textarea>
                    @error('content') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- الملاحظات --}}
                <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700">ملاحظات</label>
                    <textarea name="notes" rows="2"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-emerald-100 focus:border-[#0a5c36] rounded-2xl outline-none transition-all">{{ old('notes', $isEdit ? $question->notes : '') }}</textarea>
                    @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- أزرار الإجراءات --}}
        <div class="flex justify-end gap-4 border-t border-gray-100 pt-6 mt-6">
            <a href="{{ route('competitions.level-questions', [$competition, 'level_id' => $isEdit ? $question->competition_level_id : $selectedLevelId]) }}"
                class="px-6 py-2.5 text-gray-600 hover:bg-gray-100 rounded-xl transition-all font-bold">
                إلغاء
            </a>
            <button type="submit"
                class="px-8 py-2.5 bg-[#0a5c36] hover:bg-[#084d2d] text-white rounded-xl font-bold transition-all shadow-md">
                {{ $isEdit ? 'حفظ التعديلات' : 'حفظ السؤال' }}
            </button>
        </div>
    </form>
</div>