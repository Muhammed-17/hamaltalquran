<?php

namespace App\Http\Controllers;

use App\Enums\WelcomeMediaType;
use App\Models\WelcomeMediaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WelcomeController extends Controller
{
    public function index()
    {
        $media = WelcomeMediaItem::active()->ordered()->get();

        // لا يصل للقالب إلا ما يُعرض فعلًا: صورة ملفها موجود على القرص،
        // أو فيديو برابط يوتيوب صالح — فلا يظهر أي قسم (ولا عنوانه) بلا محتوى حقيقي
        $hasUsableImage = fn (WelcomeMediaItem $item) => $item->image_path
            && Storage::disk('public')->exists($item->image_path);

        return view('welcome', [
            'sliderItems' => $media->where('type', WelcomeMediaType::Slider)->filter($hasUsableImage)->values(),
            'galleryItems' => $media->where('type', WelcomeMediaType::Gallery)->filter($hasUsableImage)->values(),
            'videoItems' => $media->where('type', WelcomeMediaType::Video)
                ->filter(fn (WelcomeMediaItem $item) => $item->youtubeIdValue() !== null)
                ->values(),
        ]);
    }
}
