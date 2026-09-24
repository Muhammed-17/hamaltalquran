let isAdmin = false;
let teachersList = [];
let csrfToken = '';
let studentsBaseUrl = '/students';

export function initFavorites({ admin, teachers, token, baseUrl }) {
    isAdmin = admin;
    teachersList = teachers;
    csrfToken = token;
    studentsBaseUrl = baseUrl || '/students';
}

export function openFavoriteModal(studentId, isFavorited) {
    const url = `${studentsBaseUrl}/${studentId}/favorite`;

    if (isFavorited) {
        Swal.fire({
            title: 'حذف من المفضلة',
            text: 'هل تريد حذف هذا الطالب من المفضلة؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'حذف',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: '#ef4444',
        }).then((result) => {
            if (result.isConfirmed) {
                submitFavoriteForm(url, 'DELETE', {});
            }
        });
        return;
    }

    const fieldStyle = 'width: 100%; max-width: 100%; margin: 8px auto 0; box-sizing: border-box;';

    const teacherFieldHtml = isAdmin
        ? `<div id="swal-teacher-wrap" style="${fieldStyle} position: relative; text-align: right;">
                <input
                    type="text"
                    id="swal-teacher-search"
                    autocomplete="off"
                    placeholder="اختر المعلم (اختياري)..."
                    class="swal2-input"
                    style="${fieldStyle} height: 42px; font-size: 14px; margin: 0;"
                />
                <input type="hidden" id="swal-teacher-id" value="" />
           </div>`
        : '';

    Swal.fire({
        title: 'إضافة للمفضلة',
        width: 420,
        padding: '1.5rem',
        html: `
            <textarea id="swal-reason" class="swal2-textarea" placeholder="اكتب سبب إضافة الطالب للمفضلة..." style="${fieldStyle} height: 90px; font-size: 14px; resize: none;"></textarea>
            ${teacherFieldHtml}
        `,
        showCancelButton: true,
        confirmButtonText: 'إضافة',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#eab308',
        focusConfirm: false,
        customClass: {
            popup: 'swal-favorite-popup',
        },
        didOpen: () => {
            if (!isAdmin) return;

            const searchInput = document.getElementById('swal-teacher-search');
            const hiddenInput = document.getElementById('swal-teacher-id');

            // ✅ القايمة بتتعمل في document.body مباشرة (مش جوه الـ popup)
            // عشان تفلت من overflow:auto بتاع السويت اليرت، وبنستخدم
            // position: fixed عشان تتحرك مع مكان الحقل بالظبط على الشاشة.
            const optionsBox = document.createElement('div');
            optionsBox.id = 'swal-teacher-options';
            optionsBox.style.cssText = `
                display: none;
                position: fixed;
                overflow-y: auto;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                box-shadow: 0 8px 20px rgba(0,0,0,.12);
                z-index: 20000;
            `;
            document.body.appendChild(optionsBox);

            const renderOptions = (list) => {
                if (list.length === 0) {
                    optionsBox.innerHTML = `<div style="padding:10px; color:#9ca3af; font-size:13px; text-align:center;">لا توجد نتائج</div>`;
                    return;
                }
                optionsBox.innerHTML = list.map(t => `
                    <div class="swal-teacher-option" data-id="${t.id}" data-name="${t.name}"
                        style="padding:10px 14px; font-size:14px; cursor:pointer; text-align:right;">
                        ${t.name}
                    </div>
                `).join('');

                optionsBox.querySelectorAll('.swal-teacher-option').forEach(el => {
                    el.addEventListener('mouseenter', () => el.style.background = '#f9fafb');
                    el.addEventListener('mouseleave', () => el.style.background = '#fff');
                    el.addEventListener('click', () => {
                        hiddenInput.value = el.dataset.id;
                        searchInput.value = el.dataset.name;
                        optionsBox.style.display = 'none';
                    });
                });
            };

            const positionOptionsBox = () => {
                const inputRect = searchInput.getBoundingClientRect();
                const spaceBelow = window.innerHeight - inputRect.bottom;
                const spaceAbove = inputRect.top;
                const desiredHeight = 200;

                optionsBox.style.left = inputRect.left + 'px';
                optionsBox.style.width = inputRect.width + 'px';
                optionsBox.style.top = '';
                optionsBox.style.bottom = '';

                if (spaceBelow >= 100 || spaceBelow >= spaceAbove) {
                    optionsBox.style.top = inputRect.bottom + 4 + 'px';
                    optionsBox.style.maxHeight = Math.max(80, Math.min(desiredHeight, spaceBelow - 16)) + 'px';
                } else {
                    optionsBox.style.bottom = (window.innerHeight - inputRect.top + 4) + 'px';
                    optionsBox.style.maxHeight = Math.max(80, Math.min(desiredHeight, spaceAbove - 16)) + 'px';
                }
            };

            searchInput.addEventListener('focus', () => {
                renderOptions(teachersList);
                positionOptionsBox();
                optionsBox.style.display = 'block';
            });

            searchInput.addEventListener('input', () => {
                hiddenInput.value = '';
                const term = searchInput.value.trim().toLowerCase();
                const filtered = term
                    ? teachersList.filter(t => t.name.toLowerCase().includes(term))
                    : teachersList;
                renderOptions(filtered);
                positionOptionsBox();
                optionsBox.style.display = 'block';
            });

            window.addEventListener('resize', positionOptionsBox);

            document.addEventListener('click', (e) => {
                if (!document.getElementById('swal-teacher-wrap')?.contains(e.target) && e.target !== optionsBox) {
                    optionsBox.style.display = 'none';
                }
            });

            // ✅ لازم نمسح القايمة من الـ body لما المودال يتقفل، وإلا هتفضل عالقة
            Swal.getPopup()._teacherOptionsBox = optionsBox;
        },
        willClose: () => {
            const box = document.getElementById('swal-teacher-options');
            box?.remove();
        },
        preConfirm: () => {
            const reason = document.getElementById('swal-reason').value.trim();
            if (!reason) {
                Swal.showValidationMessage('لازم تكتب سبب إضافة الطالب للمفضلة');
                return false;
            }
            const teacherId = isAdmin ? document.getElementById('swal-teacher-id').value : null;
            return { reason, teacherId };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            submitFavoriteForm(url, 'POST', {
                reason: result.value.reason,
                teacher_id: result.value.teacherId || '',
            });
        }
    });
}

function submitFavoriteForm(url, method, data) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = csrfToken;
    form.appendChild(csrf);

    if (method === 'DELETE') {
        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';
        form.appendChild(methodField);
    }

    for (const key in data) {
        const field = document.createElement('input');
        field.type = 'hidden';
        field.name = key;
        field.value = data[key];
        form.appendChild(field);
    }

    document.body.appendChild(form);
    form.submit();
}

// إتاحتها كـ global functions عشان onclick="" في الـ Blade يقدر يستدعيها مباشرة
window.openFavoriteModal = openFavoriteModal;