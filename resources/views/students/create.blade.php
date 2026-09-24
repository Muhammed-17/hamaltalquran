<x-layouts.markaz-layout>
    <div class="max-w-4xl mx-auto py-8">

        <div class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-black text-[#0a5c36]">إضافة طالب جديد</h1>
                <p class="text-gray-500 mt-2">تسجيل طالب جديد في حلقات مركز حملة القرآن</p>
            </div>
            <a href="{{ route('students.index') }}"
                class="flex items-center gap-2 text-gray-500 hover:text-[#0a5c36] transition-colors font-bold">
                <span>العودة للقائمة</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>

        <form action="{{ route('students.store') }}" method="POST" novalidate id="registrationForm">
            @csrf
            @include('students.form')

            {{-- حقول مخفية يتم التحكم فيها من الـ Modal --}}
            <input type="hidden" name="send_whatsapp_message" id="sendWhatsappMessageInput" value="0">
            <input type="hidden" name="whatsapp_message_text" id="whatsappMessageTextInput" value="">
        </form>
    </div>

    {{-- ═══════════════════ Modal تأكيد إرسال رسالة الواتساب ═══════════════════ --}}
    <div id="whatsappConfirmModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-3xl shadow-xl max-w-lg w-full p-6 space-y-5" onclick="event.stopPropagation()">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 text-xl">💬</div>
                <div>
                    <h3 class="font-black text-gray-900 text-lg">إرسال رسالة ترحيب على واتساب؟</h3>
                    <p class="text-xs text-gray-400 mt-0.5">الحلقة المختارة لها رابط مجموعة واتساب مسجل</p>
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
            const btnSaveAndSend = document.getElementById('btnSaveAndSend');
            const btnSaveWithoutSend = document.getElementById('btnSaveWithoutSend');

            function getCircleUrl() {
                if (!circleSelect || !circleSelect.value) return null;
                const opt = circleSelect.options[circleSelect.selectedIndex];
                const url = opt?.getAttribute('data-url');
                return url && url.trim() !== '' ? url.trim() : null;
            }

            function openModal(defaultMessage) {
                textarea.value = defaultMessage;
                modal.classList.remove('hidden');
            }

            function closeModal() {
                modal.classList.add('hidden');
            }

            // ✅ يعطّل الأزرار ويمنع الضغط المزدوج أثناء الإرسال
            function setButtonsLoading(isLoading) {
                btnSaveAndSend.disabled = isLoading;
                btnSaveWithoutSend.disabled = isLoading;
                btnSaveAndSend.classList.toggle('opacity-50', isLoading);
                btnSaveWithoutSend.classList.toggle('opacity-50', isLoading);
            }

            // ✅ إرسال الفورم عن طريق AJAX بدل submit التقليدي
            // ده ضروري عشان window.open للواتساب يشتغل من غير ما يتحجب من المتصفح
            // (لازم يتنفذ داخل نفس الـ call stack بتاع ضغطة المستخدم مباشرة)
            async function submitFormAjax(shouldOpenWhatsapp) {
                setButtonsLoading(true);

                // ✅ افتح نافذة فاضية فورًا، جوه نفس الـ click handler، قبل أي await
                // ده الوحيد اللي بيضمن إن المتصفح ميحجبش الـ popup
                let whatsappWindow = null;
                if (shouldOpenWhatsapp) {
                    whatsappWindow = window.open('about:blank', '_blank');
                }

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const data = await response.json();

                    if (response.status === 422) {
                        whatsappWindow?.close(); // ✅ اقفل النافذة الفاضية لو فيه أخطاء validation
                        displayValidationErrors(data.errors || {});
                        setButtonsLoading(false);
                        return;
                    }
                    if (!response.ok || !data.success) {
                        whatsappWindow?.close();
                        showError(data.message || 'حدث خطأ أثناء الحفظ');
                        setButtonsLoading(false);
                        return;
                    }

                    clearValidationErrors();

                    // ✅ وجّه النافذة اللي اتفتحت فعلاً لرابط الواتساب الحقيقي
                    if (whatsappWindow && data.whatsapp_link) {
                        whatsappWindow.location.href = data.whatsapp_link;
                    } else {
                        whatsappWindow?.close();
                    }

                    window.location.href = data.redirect;
                } catch (err) {
                    whatsappWindow?.close();
                    showError('حدث خطأ في الاتصال بالسيرفر');
                    setButtonsLoading(false);
                }
            }

            form.addEventListener('submit', function(e) {
                // ✅ الإرسال بقى بيتم دايمًا عن طريق الأزرار (AJAX)، فامنع أي submit تقليدي للفورم
                e.preventDefault();

                const url = getCircleUrl();
                const hasWhatsapp = whatsappInput && whatsappInput.value.trim() !== '';

                if (url && hasWhatsapp) {
                    const defaultMessage = 'تم إضمامكم إلى مركز حملة القرآن يرجى الضغط على الرابط للدخول إلى المجموعة\n' + url;
                    openModal(defaultMessage);
                } else {
                    // ✅ لو مفيش رابط أو مفيش رقم واتساب، ابعت مباشرة من غير Modal
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

            // ✅ إغلاق الـ Modal بالنقر على الخلفية (خارج الصندوق الأبيض)
            modal.addEventListener('click', function() {
                closeModal();
            });

            // ✅ عرض كل خطأ تحت الحقل الخاص به بدل رسالة واحدة مجمعة
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

                // ✅ يمرر تلقائيًا لأول حقل فيه خطأ عشان المستخدم يشوفه
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