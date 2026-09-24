<x-layouts.markaz-layout>
    <div class="max-w-5xl mx-auto py-8">

        <div class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-black text-[#0a5c36]">تعديل بيانات الطالب</h1>
                <p class="text-gray-500 mt-2">تحديث بيانات: {{ $student->name }}</p>
            </div>
            <a href="{{ route('students.show', $student->id) }}"
                class="flex items-center gap-2 text-gray-500 hover:text-[#0a5c36] transition-colors font-bold">
                <span>العودة للملف</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>

        <form action="{{ route('students.update', $student->id) }}" method="POST" novalidate id="registrationForm">
            @csrf
            @method('PUT')
            @include('students.form')

            {{-- ✅ القيمة الأصلية للحلقة وقت فتح الصفحة — تُستخدم للمقارنة عند الإرسال --}}
            <input type="hidden" id="originalCircleId" value="{{ old('circle_id', $student->circle_id ?? '') }}">

            {{-- حقول مخفية يتم التحكم فيها من الـ Modal --}}
            <input type="hidden" name="send_whatsapp_message" id="sendWhatsappMessageInput" value="0">
            <input type="hidden" name="whatsapp_message_text" id="whatsappMessageTextInput" value="">
        </form>

    </div>

    {{-- ═══════════════════ Modal تأكيد إرسال رسالة الواتساب عند تغيير الحلقة ═══════════════════ --}}
    <div id="whatsappConfirmModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-3xl shadow-xl max-w-lg w-full p-6 space-y-5" onclick="event.stopPropagation()">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 text-xl">💬</div>
                <div>
                    <h3 class="font-black text-gray-900 text-lg">إرسال رسالة ترحيب على واتساب؟</h3>
                    <p class="text-xs text-gray-400 mt-0.5">تم تغيير حلقة الطالب، والحلقة الجديدة لها رابط مجموعة واتساب مسجل</p>
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-bold text-gray-700">نص الرسالة (قابل للتعديل)</label>
                <textarea id="whatsappMessageTextarea" rows="4"
                    class="w-full p-3 bg-gray-50 border border-gray-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#0a5c36] focus:ring-1 focus:ring-[#0a5c36] transition-all"></textarea>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <button type="button" id="btnSaveAndSend"
                    class="flex-1 px-4 py-3 bg-[#0a5c36] hover:bg-[#084d2d] text-white font-black rounded-2xl text-sm transition-all">
                    حفظ البيانات وإرسال الرسالة
                </button>
                <button type="button" id="btnSaveWithoutSend"
                    class="flex-1 px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-black rounded-2xl text-sm transition-all">
                    حفظ مع عدم الإرسال
                </button>
                <button type="button" id="btnCancelModal"
                    class="px-4 py-3 text-gray-400 hover:text-red-500 font-bold text-sm transition-all">
                    إلغاء
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            const modal = document.getElementById('whatsappConfirmModal');
            const textarea = document.getElementById('whatsappMessageTextarea');
            const sendInput = document.getElementById('sendWhatsappMessageInput');
            const textInput = document.getElementById('whatsappMessageTextInput');
            const circleSelect = document.getElementById('circleSelect');
            const whatsappInput = document.getElementById('whatsappInput');
            const originalCircleIdInput = document.getElementById('originalCircleId');
            const btnSaveAndSend = document.getElementById('btnSaveAndSend');
            const btnSaveWithoutSend = document.getElementById('btnSaveWithoutSend');

            function getCircleUrl() {
                if (!circleSelect || !circleSelect.value) return null;
                const opt = circleSelect.options[circleSelect.selectedIndex];
                const url = opt?.getAttribute('data-url');
                return url && url.trim() !== '' ? url.trim() : null;
            }

            // ✅ الشرط الأساسي هنا: الحلقة اتغيرت فعليًا عن القيمة الأصلية وقت فتح الصفحة
            function circleWasChanged() {
                if (!circleSelect) return false;
                const original = (originalCircleIdInput?.value ?? '').toString();
                const current = (circleSelect.value ?? '').toString();
                return current !== original && current !== '';
            }

            function openModal(defaultMessage) {
                textarea.value = defaultMessage;
                modal.classList.remove('hidden');
            }

            function closeModal() {
                modal.classList.add('hidden');
            }

            function setButtonsLoading(isLoading) {
                btnSaveAndSend.disabled = isLoading;
                btnSaveWithoutSend.disabled = isLoading;
                btnSaveAndSend.classList.toggle('opacity-50', isLoading);
                btnSaveWithoutSend.classList.toggle('opacity-50', isLoading);
            }

            async function submitFormAjax(shouldOpenWhatsapp) {
                setButtonsLoading(true);
                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST', // FormData مع _method=PUT بيتبعت POST دايمًا
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const data = await response.json();

                    if (response.status === 422) {
                        displayValidationErrors(data.errors || {});
                        setButtonsLoading(false);
                        return;
                    }
                    if (!response.ok || !data.success) {
                        showError(data.message || 'حدث خطأ أثناء الحفظ');
                        setButtonsLoading(false);
                        return;
                    }

                    clearValidationErrors();

                    if (shouldOpenWhatsapp && data.whatsapp_link) {
                        window.open(data.whatsapp_link, '_blank');
                    }

                    window.location.href = data.redirect;
                } catch (err) {
                    showError('حدث خطأ في الاتصال بالسيرفر');
                    setButtonsLoading(false);
                }
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const url = getCircleUrl();
                const hasWhatsapp = whatsappInput && whatsappInput.value.trim() !== '';
                const changed = circleWasChanged();

                // ✅ يظهر الـ Modal فقط لو: الحلقة اتغيرت + الحلقة الجديدة عندها رابط + فيه رقم واتساب
                if (changed && url && hasWhatsapp) {
                    const defaultMessage = 'تم إضمامكم إلى مركز حملة القرآن يرجى الضغط على الرابط للدخول إلى المجموعة\n' + url;
                    openModal(defaultMessage);
                } else {
                    sendInput.value = '0';
                    textInput.value = '';
                    submitFormAjax(false);
                }
            });

            btnSaveAndSend.addEventListener('click', function() {
                sendInput.value = '1';
                textInput.value = textarea.value;
                closeModal();
                submitFormAjax(true);
            });

            btnSaveWithoutSend.addEventListener('click', function() {
                sendInput.value = '0';
                textInput.value = '';
                closeModal();
                submitFormAjax(false);
            });

            document.getElementById('btnCancelModal').addEventListener('click', function() {
                closeModal();
            });

            modal.addEventListener('click', function() {
                closeModal();
            });

            function clearValidationErrors() {
                document.querySelectorAll('[data-error-for]').forEach(el => {
                    el.textContent = '';
                    el.classList.add('hidden');
                });
            }

            function displayValidationErrors(errors) {
                clearValidationErrors();
                Object.keys(errors).forEach(field => {
                    const el = document.querySelector(`[data-error-for="${field}"]`);
                    const message = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
                    if (el) {
                        el.textContent = message;
                        el.classList.remove('hidden');
                    }
                });

                const firstErrorField = Object.keys(errors)[0];
                const firstEl = firstErrorField ? document.querySelector(`[data-error-for="${firstErrorField}"]`) : null;
                firstEl?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
    </script>
</x-layouts.markaz-layout>