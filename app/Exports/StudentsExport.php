<?php

namespace App\Exports;

use App\Support\SqlExpressions;

use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private array $filters) {}

    public function query()
    {
        $user  = Auth::user();
        $query = Student::query()
            ->select([
                'id',
                'name',
                'status',
                'decision',
                'circle_id',
                'center_id',
                'educational_stage',
                'school_grade',
                'date_of_birth',
                'student_code',
                'whatsapp_number',
            ])
            ->with('circle:id,name');

        if ($user->hasRole('guardian')) {
            $query->where('guardian_id', $user->id);
        }

        if ($user->hasRole('supervisor') || $user->hasRole('teacher')) {
            $query->where('status', 'مقيد');
        }

        $f = $this->filters;

        $query
            ->when($f['q'] ?? null, fn($q, $v) => $q->where(
                fn($q) => $q
                    ->where('name', 'like', "%$v%")
                    ->orWhere('student_code', 'like', "%$v%")
                    ->orWhere('whatsapp_number', 'like', "%$v%")
            ))
            ->when($f['status'] ?? null,            fn($q, $v) => $q->where('status', $v))
            ->when($f['circle_id'] ?? null,          fn($q, $v) => $q->where('circle_id', $v))
            ->when($f['center_id'] ?? null,          fn($q, $v) => $q->where('center_id', $v))
            ->when($f['educational_stage'] ?? null,  fn($q, $v) => $q->where('educational_stage', $v))
            ->when($f['school_grade'] ?? null,       fn($q, $v) => $q->where('school_grade', $v))
            ->when($f['decision'] ?? null,           fn($q, $v) => $q->where('decision', $v))
            ->when($f['age_min'] ?? null, fn($q, $v) => $q->whereRaw(
                SqlExpressions::ageYears('date_of_birth') . ' >= ?',
                [max(1, min(99, (int) $v))]
            ))
            ->when($f['age_max'] ?? null, fn($q, $v) => $q->whereRaw(
                SqlExpressions::ageYears('date_of_birth') . ' <= ?',
                [max(1, min(99, (int) $v))]
            ));

        return $query->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'الاسم',
            'كود الطالب',
            'الحالة',
            'قرار الإدارة',
            'الحلقة',
            'المرحلة الدراسية',
            'الصف الدراسي',
            'العمر',
            'رقم واتساب',
        ];
    }

    public function map($student): array
    {
        return [
            $student->name,
            $student->student_code,
            $student->status?->value,
            $student->decision?->value,
            $student->circle?->name ?? '—',
            $student->educational_stage?->value ?? '—',
            $student->school_grade?->value ?? '—',
            $student->date_of_birth?->age ?? '—',
            $student->whatsapp_number,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
