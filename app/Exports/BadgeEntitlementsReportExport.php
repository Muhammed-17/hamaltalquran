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
 * يُبنى من نفس استعلام الفلاتر الحالية. الورقة الأولى صف واحد لكل طالب
 * والأنواع بجانب بعضها (مستحق/خد لكل نوع) ثم إجمالي الطالب، والثانية
 * ملخص بإجمالي كل نوع — وكل ورقة تنتهي بصف «الإجمالي العام».
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

class BadgeEntitlementsDetailSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
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

        // المستحق لكل طالب في كل نوع
        $entitledPerType = (clone $base)
            ->selectRaw('student_id, badge_type_id, SUM(quantity) as qty')
            ->groupBy('student_id', 'badge_type_id')->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('qty', 'badge_type_id'));

        // اللي كل طالب خده فعليًا من كل نوع (الموزّع)
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

        $rows = collect($entitledPerType->keys())->map(function ($studentId) use ($entitledPerType, $takenPerType, $distributorPerStudent, $teacherNames, $students, $types) {
            $student = $students[$studentId] ?? null;
            $dist = $distributorPerStudent[$studentId] ?? null;
            $distributor = $dist && (int) $dist->teachers_count === 1
                ? ($teacherNames[$dist->teacher_id] ?? '—')
                : 'متعدد';

            $row = [$student?->name, $student?->circle?->name, $distributor];
            $totalEntitled = 0;
            $totalTaken = 0;
            foreach ($types as $t) {
                $ent = (int) ($entitledPerType[$studentId][$t->id] ?? 0);
                $taken = (int) ($takenPerType[$studentId][$t->id] ?? 0);
                $row[] = $ent;
                $row[] = $taken;
                $totalEntitled += $ent;
                $totalTaken += $taken;
            }
            $row[] = $totalEntitled;
            $row[] = $totalTaken;
            $row[] = $totalEntitled - $totalTaken;

            return $row;
        })->sortBy(0)->values();

        // صف الإجمالي العام: مجموع كل عمود
        $columnCount = 3 + ($types->count() * 2) + 3;
        $grand = ['الإجمالي العام', '', ''];
        for ($i = 3; $i < $columnCount; $i++) {
            $grand[] = (int) $rows->sum(fn ($r) => $r[$i]);
        }
        $rows->push($grand);

        return $rows;
    }

    public function headings(): array
    {
        $headings = ['الطالب', 'الحلقة', 'المسؤول عن التوزيع'];
        foreach ($this->typesInScope() as $t) {
            $headings[] = $t->name . ' — مستحق';
            $headings[] = $t->name . ' — خد';
        }
        $headings[] = 'إجمالي المستحق';
        $headings[] = 'إجمالي الموزّع';
        $headings[] = 'إجمالي المتبقي';

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        $studentsWithData = ($this->baseQuery)()->distinct()->count('student_id');
        $totalsRow = $studentsWithData + 2; // العناوين + صف لكل طالب + صف الإجمالي

        return [
            1 => ['font' => ['bold' => true]],
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
