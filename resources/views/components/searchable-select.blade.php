@props([
'name',
'options' => [],
'placeholder' => 'اختر...',
'searchPlaceholder' => 'ابحث...',
'defaultValue' => '',
'neutralPlaceholder' => false,
])


<div
    x-data="searchableSelect('{{ $name }}', @js($options), @js($defaultValue), @js($placeholder), @js($searchPlaceholder))"
    {{ $attributes->merge(['class' => 'relative w-full']) }}
    @click.away="close()">
    <!-- Trigger -->
    <div
        x-ref="trigger"
        @click="toggle()"
        class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm bg-white cursor-pointer flex items-center justify-between transition-colors hover:border-gray-300"
        :class="{ 'border-orange-500 ring-2 ring-orange-100': open }">
        <span
            x-text="selectedLabel || placeholder"
            :class="selectedLabel ? 'text-gray-800' : 'text-gray-400'"></span>
        <div class="flex items-center gap-2">
            <i
                x-show="selectedVal"
                @click.stop="clearSelection()"
                class="fas fa-times text-gray-400 hover:text-red-500 text-xs transition-colors"
                style="display: none;"></i>
            <i
                class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200"
                :class="{ 'rotate-180': open }"></i>
        </div>
    </div>

    <!-- Dropdown (Teleported to body to escape any overflow-hidden ancestor) -->
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.away="close()"
            :style="dropdownStyle"
            class="absolute z-[9999] bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-hidden flex flex-col"
            style="display: none;">
            <!-- Search -->
            <div class="p-2 border-b border-gray-100 sticky top-0 bg-white">
                <div class="relative">
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input
                        type="text"
                        x-ref="searchInput"
                        x-model="search"
                        @input="filter()"
                        :placeholder="searchPlaceholder"
                        class="w-full border border-gray-200 rounded-lg pr-8 pl-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none"
                        @click.stop />
                </div>
            </div>

            <!-- Options -->
            <div class="overflow-y-auto flex-1 py-1">
                <template x-if="filteredOptions.length === 0">
                    <div class="px-4 py-3 text-gray-400 text-sm text-center">
                        <i class="fas fa-inbox mb-1 block"></i>
                        <span>لا توجد نتائج</span>
                    </div>
                </template>

                <template x-for="option in filteredOptions" :key="option.value">
                    <div
                        @click="select(option)"
                        class="px-4 py-2.5 text-sm cursor-pointer flex items-center justify-between transition-colors"
                        :class="isSelected(option) ? 'bg-orange-50 text-orange-600' : 'text-gray-700 hover:bg-gray-50'">
                        <span x-text="option.label"></span>
                        <i
                            x-show="isSelected(option)"
                            class="fas fa-check text-orange-500 text-xs"></i>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <!-- Hidden Input -->
    <input type="hidden" :name="name" :value="selectedVal" />
</div>

@pushOnce('scripts')
<script>
    function searchableSelect(name, options, defaultValue, placeholder, searchPlaceholder) {
        return {
            name: name,
            allOptions: options,
            filteredOptions: [...options],
            selectedVal: defaultValue || '',
            selectedLabel: '',
            search: '',
            open: false,
            dropdownStyle: '',
            placeholder: placeholder,
            searchPlaceholder: searchPlaceholder,

            updateDropdownPosition() {
                const rect = this.$refs.trigger.getBoundingClientRect();
                this.dropdownStyle = `top: ${rect.bottom + window.scrollY + 4}px; left: ${rect.left + window.scrollX}px; width: ${rect.width}px;`;
            },

            handleReposition() {
                if (this.open) {
                    this.updateDropdownPosition();
                }
            },

            init() {
                this.updateLabel();
                this.filter();

                // ✅ إعادة حساب موقع الـ dropdown أثناء التمرير أو تغيير حجم النافذة
                // وهو مفتوح، عشان يفضل ملتصق بالـ trigger بدل ما "يطير" بعيد عنه.
                window.addEventListener('scroll', () => this.handleReposition(), true);
                window.addEventListener('resize', () => this.handleReposition());

                // ✅ تحديث الخيارات من الخارج - مع دعم preserveSelection
                window.addEventListener('update-options', (e) => {
                    if (e.detail.name === this.name) {
                        this.updateOptions(e.detail.options, e.detail.preserveSelection || false);
                    }
                });

                // ✅ تحديث القيمة من الخارج
                window.addEventListener('update-value', (e) => {
                    if (e.detail.name === this.name) {
                        this.selectedVal = String(e.detail.value);
                        this.updateLabel();
                    }
                });

                // ✅ مسح الاختيار من الخارج
                window.addEventListener('clear-selection', (e) => {
                    if (e.detail.name === this.name) {
                        this.selectedVal = '';
                        this.selectedLabel = this.placeholder;
                    }
                });
            },

            // ✅ تحديث الخيارات مع دعم الحفاظ على الاختيار
            updateOptions(newOptions, preserveSelection = false) {
                this.allOptions = newOptions;

                // إذا كان preserveSelection = true، لا تمسح الاختيار
                if (preserveSelection) {
                    this.filter();
                    this.updateLabel();
                    return;
                }

                // السلوك الأصلي: مسح الاختيار إذا لم يُعثر عليه
                const found = this.allOptions.find(o => String(o.value) === String(this.selectedVal));
                if (!found && this.selectedVal) {
                    this.selectedVal = '';
                    this.selectedLabel = this.placeholder;
                }

                this.filter();
            },

            updateLabel() {
                const found = this.allOptions.find(o => String(o.value) === String(this.selectedVal));
                this.selectedLabel = found ? found.label : '';
            },

            filter() {
                if (!this.search) {
                    this.filteredOptions = [...this.allOptions];
                    return;
                }
                const term = this.search.toLowerCase();
                this.filteredOptions = this.allOptions.filter(opt =>
                    opt.label.toLowerCase().includes(term)
                );
            },

            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.updateDropdownPosition();
                    this.$nextTick(() => this.$refs.searchInput?.focus());
                }
            },

            close() {
                this.open = false;
                this.search = '';
                this.filter();
            },

            select(option) {
                this.selectedVal = String(option.value);
                this.selectedLabel = option.label;
                this.close();

                // ✅ بث حدث "input" أصلي على العنصر نفسه عشان x-model يشتغل صح
                // Alpine's x-model على العناصر غير الأصلية (مش input/select/textarea)
                // بيستنى حدث "input" على نفس العنصر ($el) ياخد قيمته من event.detail
                this.$el.dispatchEvent(new CustomEvent('input', {
                    detail: this.selectedVal,
                    bubbles: false
                }));

                // ✅ إرسال حدث التغيير العام (يفضل موجود لأي استخدامات تانية)
                window.dispatchEvent(new CustomEvent('searchable-change', {
                    detail: {
                        name: this.name,
                        value: this.selectedVal,
                        label: this.selectedLabel
                    }
                }));
            },

            clearSelection() {
                this.selectedVal = '';
                this.selectedLabel = '';
                this.search = '';
                this.filter();

                this.$el.dispatchEvent(new CustomEvent('input', {
                    detail: this.selectedVal,
                    bubbles: false
                }));

                window.dispatchEvent(new CustomEvent('searchable-change', {
                    detail: {
                        name: this.name,
                        value: '',
                        label: ''
                    }
                }));
            },

            isSelected(option) {
                return String(this.selectedVal) === String(option.value);
            }
        }
    }
</script>
@endPushOnce