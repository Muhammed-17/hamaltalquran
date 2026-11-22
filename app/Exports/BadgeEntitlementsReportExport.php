<?php

namespace App\Exports;

use App\Models\Badges\BadgeDistribution;
use App\Models\Badges\BadgeType;
use Closure;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * تقرير الاستحقاقات والتوزيعات (زر «التقرير» في صفحة الاستحقاقات):
 * يُبنى من نفس استعلام الفلاتر الحالية، وبنفس تصميم الملف النموذجي الذي
 * أرسله المستخدم: صف واحد لكل طالب، واسم كل نوع عنوان مدموج فوق ثلاثة
 * أعمدة «المستحق» (المتبقي له) و«المستلم» (ما استلمه فعليًا) و«الإجمالي»
 * (صيغة حية = المستحق + المستلم)، ثم أعمدة «إجمالي المستحق/الموزّع/المتبقي»
 * بصيغ تجمع أعمدة الأنواع، وصف أخير «الإجمالي» بصيغ مجموع لكل عمود.
 * والورقة الثانية ملخص بإجمالي كل نوع.
 */
class BadgeEntitlementsReportExport implements WithMultipleSheets
{
    public function __construct(private Closure $baseQuery) {}

    public function sheets(): array
    {
        return [
            new BadgeEntitlementsDetailSheet($this->baseQuery),
            new BadgeEntitlementsSummarySheet($this->baseQuery),
        ];
    }
}

class BadgeEntitlementsDetailSheet implements FromCollection, WithHeadings, WithStyles, WithTitle, \Maatwebsite\Excel\Concerns\WithEvents
{
    public function __construct(private Closure $baseQuery) {}

    public function title(): string
    {
        return 'الاستحقاقات والتوزيعات';
    }

    /** الأنواع الظاهرة في النطاق الحالي (أعمدة بجانب بعضها) مرتبة بالاسم */
    private function typesInScope()
    {
        $typeIds = ($this->baseQuery)()->distinct()->pluck('badge_type_id');

        return BadgeType::whereIn('id', $typeIds)->orderBy('name')->get();
    }

    public function collection()
    {
        $base = ($this->baseQuery)();
        $ids = (clone $base)->select('id');
        $types = $this->typesInScope();

        // المستحق الكلي لكل طالب في كل نوع
        $entitledPerType = (clone $base)
            ->selectRaw('student_id, badge_type_id, SUM(quantity) as qty')
            ->groupBy('student_id', 'badge_type_id')->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('qty', 'badge_type_id'));

        // المستلم فعليًا لكل طالب من كل نوع (الموزّع)
        $takenPerType = BadgeDistribution::query()
            ->where('badge_distributions.status', 'active')
            ->join('badge_entitlements as be', 'be.id', '=', 'badge_distributions.entitlement_id')
            ->whereIn('be.id', $ids)
            ->selectRaw('be.student_id, badge_distributions.badge_type_id, SUM(badge_distributions.quantity) as qty')
            ->groupBy('be.student_id', 'badge_distributions.badge_type_id')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('qty', 'badge_type_id'));

        // مسؤول التوزيع لكل طالب (يُعرض إن كان واحدًا، وإلا «متعدد»)
        $distributorPerStudent = (clone $base)
            ->selectRaw('student_id, COUNT(DISTINCT teacher_id) as teachers_count, MAX(teacher_id) as teacher_id')
            ->groupBy('student_id')->get()->keyBy('student_id');
        $teacherNames = \App\Models\User::whereIn('id', $distributorPerStudent->pluck('teacher_id')->filter()->unique())
            ->pluck('name', 'id');

        $students = \App\Models\Student::withoutGlobalScopes()
            ->whereIn('id', $entitledPerType->keys())->with('circle:id,name')->get()->keyBy('id');

        $letter = fn (int $col) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

        $rows = collect($entitledPerType->keys())->values()->map(function ($studentId, $i) use ($entitledPerType, $takenPerType, $distributorPerStudent, $teacherNames, $students, $types, $letter) {
            $excelRow = $i + 3; // صفا العناوين أولًا
            $student = $students[$studentId] ?? null;
            $dist = $distributorPerStudent[$studentId] ?? null;
            $distributor = $dist && (int) $dist->teachers_count === 1
                ? ($teacherNames[$dist->teacher_id] ?? '—')
                : 'متعدد';

            $row = [$student?->name, $student?->circle?->name, $distributor];
            $dueCols = [];
            $takenCols = [];
            foreach ($types as $ti => $t) {
                $entitled = (int) ($entitledPerType[$studentId][$t->id] ?? 0);
                $taken = (int) ($takenPerType[$studentId][$t->id] ?? 0);
                $dueCol = 4 + ($ti * 3);
                $row[] = $entitled - $taken; // المستحق = المتبقي له
                $row[] = $taken;             // المستلم = ما استلمه فعليًا
                $row[] = '=SUM(' . $letter($dueCol) . $excelRow . ':' . $letter($dueCol + 1) . $excelRow . ')'; // إجمالي النوع
                $dueCols[] = $letter($dueCol) . $excelRow;
                $takenCols[] = $letter($dueCol + 1) . $excelRow;
            }
            // الأعمدة الختامية: صيغ حية تجمع أعمدة الأنواع
            $row[] = '=' . implode('+', $dueCols);   // إجمالي المستحق
            $row[] = '=' . implode('+', $takenCols); // إجمالي الموزّع
            $row[] = '=' . implode('+', $dueCols);   // إجمالي المتبقي (= المستحق في هذا التصميم)

            return $row;
        })->sortBy(0)->values();

        // إعادة ترقيم صيغ الإجمالي بعد الفرز الأبجدي: الصيغ مبنية على رقم الصف النهائي
        $rows = $rows->map(function ($row, $i) use ($types) {
            $excelRow = $i + 3;
            foreach ($types as $ti => $t) {
                $dueCol = 4 + ($ti * 3);
                $l1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dueCol);
                $l2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dueCol + 1);
                $row[3 + ($ti * 3) + 2] = '=SUM(' . $l1 . $excelRow . ':' . $l2 . $excelRow . ')';
            }
            $dueCols = [];
            $takenCols = [];
            foreach ($types as $ti => $t) {
                $dueCol = 4 + ($ti * 3);
                $dueCols[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dueCol) . $excelRow;
                $takenCols[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dueCol + 1) . $excelRow;
            }
            $n = count($row);
            $row[$n - 3] = '=' . implode('+', $dueCols);
            $row[$n - 2] = '=' . implode('+', $takenCols);
            $row[$n - 1] = '=' . implode('+', $dueCols);

            return $row;
        });

        // صف الإجمالي: صيغة مجموع لكل عمود رقمي
        $columnCount = 3 + ($types->count() * 3) + 3;
        $lastDataRow = $rows->count() + 2;
        $grand = ['الإجمالي', '', ''];
        for ($col = 4; $col <= $columnCount; $col++) {
            $l = $letter($col);
            $grand[] = $rows->isEmpty() ? 0 : "=SUM({$l}3:{$l}{$lastDataRow})";
        }
        $rows->push($grand);

        return $rows;
    }

    public function headings(): array
    {
        $types = $this->typesInScope();

        $row1 = ['الطالب', 'الحلقة', 'المسؤول عن التوزيع'];
        $row2 = ['', '', ''];
        foreach ($types as $t) {
            $row1[] = $t->name;
            $row1[] = '';
            $row1[] = '';
            $row2[] = 'المستحق';
            $row2[] = 'المستلم';
            $row2[] = 'الإجمالي';
        }
        $row1[] = 'إجمالي المستحق';
        $row1[] = 'إجمالي الموزّع';
        $row1[] = 'إجمالي المتبقي';
        $row2[] = '';
        $row2[] = '';
        $row2[] = '';

        return [$row1, $row2];
    }

    /** دمج اسم كل نوع فوق أعمدته الثلاثة، ودمج باقي العناوين رأسيًا، وضبط عروض الأعمدة */
    public function registerEvents(): array
    {
        $typesCount = $this->typesInScope()->count();

        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) use ($typesCount) {
                $sheet = $event->sheet->getDelegate();
                $letter = fn (int $col) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $lastColumn = 3 + ($typesCount * 3) + 3;

                foreach ([1, 2, 3, $lastColumn - 2, $lastColumn - 1, $lastColumn] as $col) {
                    $sheet->mergeCells($letter($col) . '1:' . $letter($col) . '2');
                }
                for ($i = 0; $i < $typesCount; $i++) {
                    $sheet->mergeCells($letter(4 + ($i * 3)) . '1:' . $letter(6 + ($i * 3)) . '1');
                }
                $sheet->getStyle('A1:' . $letter($lastColumn) . '2')->getAlignment()->setHorizontal('center')->setVertical('center');

                $sheet->getColumnDimension('A')->setWidth(30);
                $sheet->getColumnDimension('B')->setWidth(18);
                $sheet->getColumnDimension('C')->setWidth(20);
                for ($col = 4; $col <= $lastColumn; $col++) {
                    $sheet->getColumnDimension($letter($col))->setWidth(13);
                }
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $studentsWithData = ($this->baseQuery)()->distinct()->count('student_id');
        $totalsRow = $studentsWithData + 3; // صفا العناوين + صف لكل طالب + صف الإجمالي

        return [
            1 => ['font' => ['bold' => true]],
            2 => ['font' => ['bold' => true]],
            $totalsRow => ['font' => ['bold' => true]],
        ];
    }
}

class BadgeEntitlementsSummarySheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private Closure $baseQuery) {}

    public function title(): string
    {
        return 'ملخص حسب النوع';
    }

    public function collection()
    {
        $base = ($this->baseQuery)();
        $ids = (clone $base)->select('id');

        $entitled = (clone $base)
            ->selectRaw('badge_type_id, COUNT(*) as ops, SUM(quantity) as qty')
            ->groupBy('badge_type_id')->get()->keyBy('badge_type_id');
        $delivered = BadgeDistribution::active()
            ->whereIn('entitlement_id', $ids)
            ->selectRaw('badge_type_id, SUM(quantity) as qty')
            ->groupBy('badge_type_id')->pluck('qty', 'badge_type_id');

        $rows = BadgeType::orderBy('name')->get()
            ->filter(fn ($t) => isset($entitled[$t->id]))
            ->map(function ($t) use ($entitled, $delivered) {
                $qty = (int) $entitled[$t->id]->qty;
                $del = (int) ($delivered[$t->id] ?? 0);

                return [
                    'type' => $t->name,
                    'ops' => (int) $entitled[$t->id]->ops,
                    'entitled' => $qty,
                    'delivered' => $del,
                    'remaining' => $qty - $del,
                    'pct' => $qty > 0 ? round($del / $qty * 100) . '%' : '0%',
                ];
            })->values();

        // صف الإجمالي العام: مجموع كل الأنواع
        $totalEntitled = (int) $rows->sum('entitled');
        $totalDelivered = (int) $rows->sum('delivered');
        $rows->push([
            'type' => 'الإجمالي العام',
            'ops' => (int) $rows->sum('ops'),
            'entitled' => $totalEntitled,
            'delivered' => $totalDelivered,
            'remaining' => $totalEntitled - $totalDelivered,
            'pct' => $totalEntitled > 0 ? round($totalDelivered / $totalEntitled * 100) . '%' : '0%',
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return ['نوع الوسام', 'عدد الاستحقاقات', 'المستحق', 'الموزّع', 'المتبقي', 'نسبة الإنجاز'];
    }

    public function styles(Worksheet $sheet)
    {
        $typesWithData = ($this->baseQuery)()->distinct()->count('badge_type_id');
        $totalsRow = $typesWithData + 2; // العناوين + صف لكل نوع + صف الإجمالي

        return [
            1 => ['font' => ['bold' => true]],
            $totalsRow => ['font' => ['bold' => true]],
        ];
    }
}
