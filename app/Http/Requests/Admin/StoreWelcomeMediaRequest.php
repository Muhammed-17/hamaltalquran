<?php

namespace App\Http\Requests\Admin;

use App\Enums\WelcomeMediaType;
use App\Models\WelcomeMediaItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWelcomeMediaRequest extends FormRequest
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
            'image' => $isVideo
                ? ['nullable']
                : ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'video_url' => $isVideo
                ? ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                    if (!WelcomeMediaItem::youtubeId($value)) {
                        $fail('رابط الفيديو يجب أن يكون رابط يوتيوب صالحًا.');
                    }
                }]
                : ['nullable'],
            'in_slider' => ['nullable', 'boolean'],
            'in_gallery' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') === WelcomeMediaType::Image->value
                && !$this->boolean('in_slider') && !$this->boolean('in_gallery')) {
                $validator->errors()->add('in_slider', 'اختر مكان ظهور واحدًا على الأقل للصورة: السلايدر أو المعرض.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'اختر نوع المحتوى.',
            'image.required' => 'الصورة مطلوبة لهذا النوع.',
            'image.image' => 'الملف المرفوع يجب أن يكون صورة.',
            'image.mimes' => 'صيغ الصور المسموحة: jpg وjpeg وpng وwebp.',
            'image.max' => 'حجم الصورة لا يتجاوز 5 ميجا.',
            'video_url.required' => 'رابط الفيديو مطلوب.',
        ];
    }
}
