<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentRosterSeeder extends Seeder
{
    /**
     * ملاحظات هامة قبل التشغيل:
     * 1) شغّل CenterBranchCircleSeeder الأول.
     * 2) ضع ملف students_roster.json في: database/seeders/data/students_roster.json
     * 3) هذا الـ Seeder idempotent: يمكن تشغيله أكثر من مرة بدون فشل.
     *    - كل سجل بيتعامل معاه لوحده (مفيش transaction واحدة شاملة)، فأي
     *      خطأ في سجل واحد ما بيضيعش الباقي.
     *    - المطابقة بتتم بـ student_code. لو الكود موجود بالفعل (سواء من
     *      تشغيلة سابقة في الجدول، أو مكرر جوه الملف نفسه)، بيتسجل الطالب
     *      برقم قيد جديد غير مستخدم (يُولَّد تلقائيًا)، ويُحفظ الرقم الأصلي
     *      في notes للمراجعة اليدوية لاحقًا.
     *    - سجل بدون اسم (name = null) بيتسجل باسم مؤقت "طالب بدون اسم - {رقم القيد}"
     *      مع تحذير، بدل ما يوقف الاستيراد بالكامل.
     * 4) الحلقة بتتربط بالاسم الحرفي من circles.json. لو مفيش تطابق، الطالب
     *    بيتسجل بدون حلقة (circle_id = null) مع تحذير في نهاية التشغيل.
     * 5) النوع (gender) مُستنتج تقريبيًا من الاسم الأول — راجعه يدويًا بعد الاستيراد.
     */

    private array $femaleFirstNames = [
        'البتول','الزهراء','الشيماء','إسراء','إلين','إيمان','أروى','أريام','أريج',
        'أسماء','أسيل','أسينات','ألما','أمال','أنسام','أيتن','أيسل','أيلا','أيلن',
        'آسيا','آيات','آية','بتول','بسمة','بلقيس','بنان','بيان','بيسان','تالا',
        'تاليا','تولين','جنات','جنى','جني','جود','جودى','جوري','جورى','جويرية',
        'حبيبة','حلا','خديجة','خلود','دنيا','ديمة','رانسى','ربى','رتيل','رحمة',
        'رحمه','رزان','رسيل','رغد','رفيف','رقية','رنا','رنيم','رنين','رهف','رواء',
        'رودينا','روضة','روفان','رؤى','رؤيا','رؤية','ريتاج','ريتال','ريحانة','ريم',
        'ريماس','ريناد','زهرة','سارة','ساره','ساندي','سجى','سدين','سلمى','سليا',
        'سما','سيلا','سيلين','شمس','شهد','صباح','صفاء','صفية','عائشة','عائشه',
        'عزة','غزل','غصون','فاطمة','فرح','فيروز','كارما','كاميليا','كريمة',
        'لاتين','لارين','لانا','لمى','لوجي','لورين','ليان','ماريا','ماسة','مرام',
        'مرنا','مريم','مسك','مكة','ملك','منه','مودة','ميرا','ميرال','نادين',
        'نداء','ندى','نغم','نهر','نور','نورهان','نورين','هاجر','هبة','هنا','وجد',
        'ورد','وعد','يارا','يسر','يقين','يمنى','يمني','ياسمين',
    ];

    private array $maleFirstNames = [
        'البراء','الحسن','السيد','إبراهيم','إسلام','إسماعيل','إياد','أبوبكر',
        'أحمد','أمجد','أمير','أنس','أوبي','آدم','آسر','بدر','بلال','تيم','جمال',
        'حذيفة','حسن','حسين','حمزة','خالد','خيري','راشد','رامي','رائد','رفعت',
        'رمضان','رياض','ريان','زياد','زين','ساجد','سعد','سعيد','سفيان','سليم',
        'سمير','سيف','شادي','شحاته','شعبان','شهاب','طاهر','عاصم','عبدالبديع',
        'عبدالرحمن','عبدالعزيز','عبدالفتاح','عبدالكريم','عبدالله','عبيدة','عدنان',
        'عصام','على','علي','عمار','عمر','عمرو','غريب','فادي','فارس','فتحي',
        'فراس','فرحات','فريد','فوزي','قصي','كريم','لؤي','ماجد','مازن','مالك',
        'محسن','محمد','محمود','مراون','مروان','مصطفى','معاذ','معزالدين','موسى',
        'ناصر','وليد','ياسين','يامن','يحيى','يزيد','يوسف',
    ];

    private array $taMarbutaMaleExceptions = [
        'حمزة','أسامة','معاوية','عبيدة','طلحة','عطية','علاء','فداء',
    ];

    public function run(): void
    {
        $path = database_path('seeders/data/students_roster.json');

        if (!file_exists($path)) {
            $this->command->error("ملف البيانات غير موجود: {$path}");
            return;
        }

        $rows = json_decode(file_get_contents($path), true);

        $this->command->info('عدد السجلات المراد استيرادها: ' . count($rows));

        $femaleLookup = array_flip($this->femaleFirstNames);
        $maleLookup = array_flip($this->maleFirstNames);
        $exceptionsLookup = array_flip($this->taMarbutaMaleExceptions);

        $guessGender = function (?string $fullName) use ($femaleLookup, $maleLookup, $exceptionsLookup): string {
            if (!$fullName) {
                return 'male';
            }

            $firstName = trim(explode(' ', trim($fullName))[0]);

            if (isset($femaleLookup[$firstName])) {
                return 'female';
            }

            if (isset($maleLookup[$firstName])) {
                return 'male';
            }

            if (isset($exceptionsLookup[$firstName])) {
                return 'male';
            }

            $lastChar = mb_substr($firstName, -1);
            $lastTwoChars = mb_substr($firstName, -2);

            if ($lastChar === 'ة' || $lastTwoChars === 'اء') {
                return 'female';
            }

            return 'male';
        };

        // الحلقات الحقيقية (name => [circle_id, center_id]) — تحميل دفعة واحدة
        $circles = DB::table('circles')
            ->join('branches', 'circles.branch_id', '=', 'branches.id')
            ->select('circles.id as circle_id', 'circles.name as circle_name', 'branches.center_id')
            ->get()
            ->keyBy('circle_name');

        // أعلى رقم قيد مستخدم فعليًا (في الملف + في الجدول) لتوليد أرقام جديدة آمنة عند التعارض
        $maxFromFile = collect($rows)->pluck('registration_no')->filter()->max() ?? 0;
        $maxFromDb = (int) DB::table('students')->max(DB::raw('CAST(student_code AS UNSIGNED)'));
        $nextAvailableCode = max($maxFromFile, $maxFromDb) + 1;

        // تتبع الأكواد المستخدمة في هذه التشغيلة لمنع تصادم بين سجلين من الملف نفسه
        $usedCodes = DB::table('students')->pluck('student_code')->filter()->map(fn ($c) => (string) $c)->flip()->toArray();

        $missingCircles = [];
        $reassignedCodes = [];
        $missingNames = [];
        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $circleId = null;
            $centerId = null;

            if (!empty($row['circle_name']) && $row['circle_name'] !== 'لا يوجد') {
                $circleName = trim($row['circle_name']);
                $match = $circles->get($circleName);

                if ($match) {
                    $circleId = $match->circle_id;
                    $centerId = $match->center_id;
                } else {
                    $missingCircles[$circleName] = ($missingCircles[$circleName] ?? 0) + 1;
                }
            }

            $extraNotes = [];
            if (!empty($row['category'])) {
                $extraNotes[] = 'الفئة: ' . $row['category'];
            }
            if (!empty($row['whatsapp_status'])) {
                $extraNotes[] = 'حالة الواتس: ' . $row['whatsapp_status'];
            }
            if (!empty($row['raw_status']) && $row['raw_status'] !== $row['status']) {
                $extraNotes[] = 'الحالة الأصلية بالكشف: ' . $row['raw_status'];
            }
            if (!empty($row['notes'])) {
                $extraNotes[] = $row['notes'];
            }

            $name = $row['name'] ?? null;
            $originalRegNo = $row['registration_no'] ?? null;

            if (!$name) {
                $name = 'طالب بدون اسم - ' . ($originalRegNo ?? 'بدون رقم قيد');
                $missingNames[] = $originalRegNo;
            }

            $studentCode = $originalRegNo ? (string) $originalRegNo : null;

            // لو الكود مستخدم بالفعل (في الجدول أو في هذه التشغيلة)، نولّد رقم جديد
            if ($studentCode !== null && isset($usedCodes[$studentCode])) {
                $newCode = (string) $nextAvailableCode++;
                $reassignedCodes[] = "{$name}: {$studentCode} → {$newCode}";
                $extraNotes[] = "تم تغيير رقم القيد من {$studentCode} إلى {$newCode} بسبب تكرار";
                $studentCode = $newCode;
            }

            if ($studentCode !== null) {
                $usedCodes[$studentCode] = true;
            }

            try {
                $student = Student::updateOrCreate(
                    ['student_code' => $studentCode],
                    [
                        'name' => $name,
                        'status' => $row['status'],
                        'decision' => $row['decision'],
                        'whatsapp_number' => $row['whatsapp_number'],
                        'circle_id' => $circleId,
                        'center_id' => $centerId,
                        'education_type' => 'غير محدد',
                        'gender' => $guessGender($row['name'] ?? null),
                        'notes' => $extraNotes ? implode(' | ', $extraNotes) : null,
                    ]
                );

                $student->wasRecentlyCreated ? $created++ : $updated++;
            } catch (\Throwable $e) {
                $failed++;
                $this->command->error("فشل استيراد السجل (رقم القيد الأصلي: {$originalRegNo}, الاسم: {$name}): " . $e->getMessage());
            }
        }

        $this->command->info("تم الاستيراد — أُنشئ: {$created}، حُدّث: {$updated}، فشل: {$failed}");

        if ($missingNames) {
            $this->command->warn('سجلات بدون اسم في الكشف الأصلي (تم تسجيلها باسم مؤقت):');
            foreach ($missingNames as $regNo) {
                $this->command->warn("  - رقم القيد: " . ($regNo ?? 'غير معروف'));
            }
        }

        if ($reassignedCodes) {
            $this->command->warn('أرقام قيد تم تغييرها بسبب التكرار:');
            foreach ($reassignedCodes as $line) {
                $this->command->warn("  - {$line}");
            }
        }

        if ($missingCircles) {
            $this->command->warn('حلقات لم تُطابق أي حلقة حقيقية (تم تسجيل الطالب بدون حلقة):');
            foreach ($missingCircles as $name => $count) {
                $this->command->warn("  - \"{$name}\" ({$count} طالب)");
            }
        }
    }
}