<?php

namespace App\Http\Requests\Admin;

use App\Enums\WelcomeMediaType;
use App\Models\WelcomeMediaItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWelcomeMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        $isVideo = $this->input('type') === WelcomeMediaType::Video->value;

        return [
            'type' => ['required', Rule::enum(WelcomeMediaType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            // الصورة اختيارية عند التعديل: تُركها فارغة يُبقي الحالية
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'video_url' => $isVideo
                ? ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                    if (!WelcomeMediaItem::youtubeId($value)) {
                        $fail('رابط الفيديو يجب أن يكون رابط يوتيوب صالحًا.');
                    }
                }]
                : ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'اختر نوع المحتوى.',
            'image.image' => 'الملف المرفوع يجب أن يكون صورة.',
            'image.mimes' => 'صيغ الصور المسموحة: jpg وjpeg وpng وwebp.',
            'image.max' => 'حجم الصورة لا يتجاوز 5 ميجا.',
            'video_url.required' => 'رابط الفيديو مطلوب.',
        ];
    }
}
