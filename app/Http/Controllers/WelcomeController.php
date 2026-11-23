<?php

namespace App\Http\Controllers;

use App\Enums\WelcomeMediaType;
use App\Models\WelcomeMediaItem;
use Illuminate\Http\Request;

class WelcomeController extends Controller
{
    public function index()
    {
        $media = WelcomeMediaItem::active()->ordered()->get();

        return view('welcome', [
            'sliderItems' => $media->where('type', WelcomeMediaType::Slider)->values(),
            'galleryItems' => $media->where('type', WelcomeMediaType::Gallery)->values(),
            'videoItems' => $media->where('type', WelcomeMediaType::Video)->values(),
        ]);
    }
}
