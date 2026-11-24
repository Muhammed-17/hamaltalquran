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
            'video_source' => ['nullable', Rule::in(['link', 'file'])],
            'video_url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if ($this->input('type') === WelcomeMediaType::Video->value
                    && $this->input('video_source', 'link') === 'link'
                    && !WelcomeMediaItem::youtubeId($value)) {
                    $fail('رابط الفيديو يجب أن يكون رابط يوتيوب صالحًا.');
                }
            }],
            'video_file' => ['nullable', 'file', 'mimes:mp4,webm', 'max:102400'],
            'in_slider' => ['nullable', 'boolean'],
            'in_gallery' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') !== WelcomeMediaType::Video->value) {
                return;
            }
            $source = $this->input('video_source', 'link');
            $existing = $this->route('welcome_medium');
            if ($source === 'link') {
                if (!$this->filled('video_url') && !($existing && $existing->video_url && !$existing->video_path)) {
                    $validator->errors()->add('video_url', 'رابط الفيديو مطلوب.');
                }
            } else {
                if (!$this->hasFile('video_file') && !($existing && $existing->video_path)) {
                    $validator->errors()->add('video_file', 'ملف الفيديو مطلوب.');
                }
            }
        });

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
            'image.image' => 'الملف المرفوع يجب أن يكون صورة.',
            'image.mimes' => 'صيغ الصور المسموحة: jpg وjpeg وpng وwebp.',
            'image.max' => 'حجم الصورة لا يتجاوز 5 ميجا.',
            'video_file.mimes' => 'صيغ الفيديو المسموحة: mp4 وwebm.',
            'video_file.max' => 'حجم الفيديو لا يتجاوز 100 ميجا.',
        ];
    }
}
