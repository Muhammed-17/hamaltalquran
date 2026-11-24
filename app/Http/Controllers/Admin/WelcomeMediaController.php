<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WelcomeMediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWelcomeMediaRequest;
use App\Http\Requests\Admin\UpdateWelcomeMediaRequest;
use App\Models\WelcomeMediaItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class WelcomeMediaController extends Controller
{
    public function index(): View
    {
        $items = WelcomeMediaItem::ordered()->get();

        return view('admin.welcome_media.index', [
            'items' => $items,
            'counts' => [
                'slider' => $items->filter(fn ($i) => $i->type === WelcomeMediaType::Image && $i->in_slider)->count(),
                'gallery' => $items->filter(fn ($i) => $i->type === WelcomeMediaType::Image && $i->in_gallery)->count(),
                'video' => $items->where('type', WelcomeMediaType::Video)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.welcome_media.create', ['item' => new WelcomeMediaItem(['is_active' => true])]);
    }

    public function store(StoreWelcomeMediaRequest $request): RedirectResponse
    {
        $data = $this->payload($request->validated(), $request);

        WelcomeMediaItem::create($data);

        return redirect()->route('welcome-media.index')->with('success', 'تمت إضافة المحتوى بنجاح.');
    }

    public function edit(WelcomeMediaItem $welcome_medium): View
    {
        return view('admin.welcome_media.edit', ['item' => $welcome_medium]);
    }

    public function update(UpdateWelcomeMediaRequest $request, WelcomeMediaItem $welcome_medium): RedirectResponse
    {
        $data = $this->payload($request->validated(), $request, $welcome_medium);

        $welcome_medium->update($data);

        return redirect()->route('welcome-media.index')->with('success', 'تم تحديث المحتوى بنجاح.');
    }

    public function destroy(WelcomeMediaItem $welcome_medium): RedirectResponse
    {
        $welcome_medium->delete(); // حذف ملف الصورة يتم من حدث النموذج

        return redirect()->route('welcome-media.index')->with('success', 'تم حذف المحتوى.');
    }

    /** تجهيز بيانات الحفظ: رفع الصورة الجديدة وحذف القديمة عند الاستبدال، وضبط الحقول حسب النوع */
    private function payload(array $validated, $request, ?WelcomeMediaItem $existing = null): array
    {
        $type = WelcomeMediaType::from($validated['type']);

        $data = [
            'type' => $type->value,
            'in_slider' => $type === WelcomeMediaType::Image && $request->boolean('in_slider'),
            'in_gallery' => $type === WelcomeMediaType::Image && $request->boolean('in_gallery'),
            'title' => $validated['title'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($type === WelcomeMediaType::Video) {
            $data['image_path'] = null;
            if ($existing?->image_path) {
                Storage::disk('public')->delete($existing->image_path);
            }

            $source = $request->input('video_source', 'link');
            if ($source === 'file') {
                $data['video_url'] = null;
                if ($request->hasFile('video_file')) {
                    if ($existing?->video_path) {
                        Storage::disk('public')->delete($existing->video_path);
                    }
                    $data['video_path'] = $request->file('video_file')->store('welcome-media/videos', 'public');
                } elseif ($existing) {
                    $data['video_path'] = $existing->video_path;
                }
            } else {
                $data['video_path'] = null;
                if ($existing?->video_path) {
                    Storage::disk('public')->delete($existing->video_path);
                }
                $data['video_url'] = $validated['video_url'] ?? $existing?->video_url;
            }
        } else {
            $data['video_url'] = null;
            if ($request->hasFile('image')) {
                if ($existing?->image_path) {
                    Storage::disk('public')->delete($existing->image_path);
                }
                $data['image_path'] = $request->file('image')->store('welcome-media', 'public');
            } elseif ($existing) {
                $data['image_path'] = $existing->image_path;
            }
        }

        return $data;
    }
}
