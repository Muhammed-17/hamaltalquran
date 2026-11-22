<?php

namespace App\Exports;

use App\Models\Badges\BadgeType;
use App\Models\User;
use Closure;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * تقرير تسليمات العهدة (زر «التقرير» في صفحة التسليمات):
 * يُبنى من نفس استعلام فلاتر الصفحة. الورقة الأولى مجمّعة: صف واحد
 * لكل (شهر + مستلم) — اسم المستلم يظهر مرة واحدة وكميات كل الأنواع
 * بجانب بعضها في نفس الصف ثم الإجمالي والملاحظات، وبلا صف إجمالي
 * أخير. التسليمات الملغاة مستبعدة (لا تحرّك عهدة). والثانية ملخص حسب النوع.
 */
class BadgeHandoversReportExport implements WithMultipleSheets
{
    public function __construct(private Closure $baseQuery) {}

    public function sheets(): array
    {
        return [
            new BadgeHandoversDetailSheet($this->baseQuery),
            new BadgeHandoversSummarySheet($this->baseQuery),
        ];
    }
}

class BadgeHandoversDetailSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private Closure $baseQuery) {}

    public function title(): string
    {
        return 'تسليمات العهدة';
    }

    /** التسليمات النشطة فقط داخل فلاتر الصفحة (الملغاة لا تحرّك عهدة) */
    private function activeFiltered()
    {
        return ($this->baseQuery)()->where('status', 'active');
    }

    /** الأنواع الظاهرة في النطاق الحالي (عمود لكل نوع) مرتبة بالاسم */
    private function typesInScope()
    {
        $typeIds = $this->activeFiltered()->distinct()->pluck('badge_type_id');

        return BadgeType::whereIn('id', $typeIds)->orderBy('name')->get();
    }

    public function collection()
    {
        $types = $this->typesInScope();

        // صف واحد لكل (شهر + مستلم): اسم المستلم يظهر مرة واحدة، وكميات
        // كل الأنواع بجانب بعضها في نفس الصف — لا صف منفصل لكل تسليم
        $groups = [];
        foreach ($this->activeFiltered()->with('teacher')->get() as $h) {
            $month = $h->handover_date?->format('Y-m');
            $key = $month . '|' . $h->teacher_id;
            $groups[$key] ??= [
                'month' => $month,
                'teacher' => $h->teacher?->name,
                'qty' => [],
                'notes' => [],
            ];
            $groups[$key]['qty'][$h->badge_type_id] = ($groups[$key]['qty'][$h->badge_type_id] ?? 0) + (int) $h->quantity;
            if ($h->notes) {
                $groups[$key]['notes'][] = $h->notes;
            }
        }

        return collect($groups)
            ->sortBy([['month', 'desc'], ['teacher', 'asc']])
            ->map(function ($g) use ($types) {
                $row = [$g['month'], $g['teacher']];
                $total = 0;
                foreach ($types as $t) {
                    $qty = $g['qty'][$t->id] ?? null;
                    $row[] = $qty;
                    $total += (int) $qty;
                }
                $row[] = $total; // الإجمالي
                $row[] = implode('؛ ', array_unique($g['notes']));

                return $row;
            })
            ->values();
    }

    public function headings(): array
    {
        return array_merge(
            ['الشهر', 'المستلم'],
            $this->typesInScope()->map(fn ($t) => $t->name)->all(),
            ['الإجمالي', 'ملاحظات']
        );
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

class BadgeHandoversSummarySheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private Closure $baseQuery) {}

    public function title(): string
    {
        return 'ملخص حسب النوع';
    }

    public function collection()
    {
        $active = ($this->baseQuery)()->where('status', 'active')
            ->selectRaw('badge_type_id, COUNT(*) as ops, SUM(quantity) as qty')
            ->groupBy('badge_type_id')->get()->keyBy('badge_type_id');

        $rows = BadgeType::orderBy('name')->get()
            ->filter(fn ($t) => isset($active[$t->id]))
            ->map(fn ($t) => [
                'type' => $t->name,
                'ops' => (int) $active[$t->id]->ops,
                'quantity' => (int) $active[$t->id]->qty,
            ])->values();

        $rows->push([
            'type' => 'الإجمالي العام',
            'ops' => (int) $rows->sum('ops'),
            'quantity' => (int) $rows->sum('quantity'),
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return ['نوع الوسام', 'عدد التسليمات النشطة', 'إجمالي الكمية'];
    }

    public function styles(Worksheet $sheet)
    {
        $typesWithData = ($this->baseQuery)()->where('status', 'active')->distinct()->count('badge_type_id');
        $totalsRow = $typesWithData + 2; // العناوين + صف لكل نوع + صف الإجمالي

        return [
            1 => ['font' => ['bold' => true]],
            $totalsRow => ['font' => ['bold' => true]],
        ];
    }
}
