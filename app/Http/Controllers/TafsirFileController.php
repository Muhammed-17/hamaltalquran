<?php

namespace App\Http\Controllers;

use App\Http\Requests\TafsirFile\StoreTafsirFileRequest;
use App\Http\Requests\TafsirFile\UpdateTafsirFileRequest;
use App\Models\TafsirFile;

class TafsirFileController extends Controller
{
    /**
     * Display a listing of tafsir files.
     */
    public function index()
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $tafsirFiles = TafsirFile::query()
            ->orderBy('name')
            ->paginate(15);

        return view('tafsir_files.index', compact('tafsirFiles'));
    }

    /**
     * Show the form for creating a new tafsir file.
     */
    public function create()
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        return view('tafsir_files.create');
    }

    /**
     * Store a newly created tafsir file.
     */
    public function store(StoreTafsirFileRequest $request)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        TafsirFile::create($request->validated());

        return redirect()
            ->route('tafsir-files.index')
            ->with('success', 'تم إضافة ملف التفسير بنجاح.');
    }

    /**
     * Show the form for editing the specified tafsir file.
     */
    public function edit(TafsirFile $tafsirFile)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        return view('tafsir_files.edit', compact('tafsirFile'));
    }

    /**
     * Update the specified tafsir file.
     */
    public function update(
        UpdateTafsirFileRequest $request,
        TafsirFile $tafsirFile
    ) {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $validated = $request->validated();

        // ✅ FIX: طبقة حماية إضافية (server-side) — لو مفيش أي تغيير فعلي
        // على بيانات ملف التفسير، تجاهل التحديث بدل عمل UPDATE query غير ضروري.
        // ده احتياطي فقط؛ المنع الأساسي المفروض يكون بالـ JS في tafsir_files/edit.blade.php
        if (!$this->hasTafsirFileChanges($tafsirFile, $validated)) {
            return redirect()
                ->route('tafsir-files.index')
                ->with('info', 'لم يتم إجراء أي تعديل.');
        }

        $tafsirFile->update($validated);

        return redirect()
            ->route('tafsir-files.index')
            ->with('success', 'تم تحديث ملف التفسير بنجاح.');
    }

    /**
     * Remove the specified tafsir file.
     */
    public function destroy(TafsirFile $tafsirFile)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $tafsirFile->delete();

        return redirect()
            ->route('tafsir-files.index')
            ->with('success', 'تم حذف ملف التفسير بنجاح.');
    }

    private function hasTafsirFileChanges(TafsirFile $tafsirFile, array $validated): bool
    {
        foreach ($validated as $key => $value) {
            if ((string) ($tafsirFile->{$key} ?? '') !== (string) ($value ?? '')) {
                return true;
            }
        }

        return false;
    }
}
