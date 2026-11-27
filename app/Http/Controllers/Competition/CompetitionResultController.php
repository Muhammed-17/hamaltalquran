<?php

namespace App\Http\Controllers\Competition;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionParticipant;
use Illuminate\Http\Request;

class CompetitionResultController extends Controller
{
    public function index(Competition $competition, Request $request)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $results = CompetitionParticipant::query()
            ->where('competition_id', $competition->id)
            ->with(['competitionLevel.level', 'student', 'externalParticipant'])
            ->withSum('competitionAnswers', 'score')
            ->when($request->filled('level_id'), function ($query) use ($request) {
                $query->where('competition_level_id', $request->level_id);
            })
            ->get()
            ->sortByDesc('competition_answers_sum_score')
            ->values();

        $levels = $competition->competitionLevels()->with('level')->get();

        return view('competitions.results', compact('competition', 'results', 'levels'));
    }

    public function exportExcel(Competition $competition, Request $request)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $results = CompetitionParticipant::query()
            ->where('competition_id', $competition->id)
            ->with(['competitionLevel.level', 'student', 'externalParticipant'])
            ->withSum('competitionAnswers', 'score')
            ->when($request->filled('level_id'), function ($query) use ($request) {
                $query->where('competition_level_id', $request->level_id);
            })
            ->get()
            ->sortByDesc('competition_answers_sum_score')
            ->values();

        $filename = 'نتائج-' . $competition->name . '-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($results) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
            fputcsv($handle, ['الترتيب', 'الاسم', 'المستوى', 'المجموع']);

            foreach ($results as $index => $result) {
                $name = $result->student->name ?? $result->externalParticipant->name ?? '-';
                fputcsv($handle, [
                    $index + 1,
                    $name,
                    $result->competitionLevel->level->name ?? '-',
                    $result->competition_answers_sum_score ?? 0,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
