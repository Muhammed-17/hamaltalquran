<?php

/**
 * محتوى الصفحة الرئيسية المدار من لوحة التحكم: سلايدر ومعرض وفيديوهات.
 * إدارة أدمن فقط، والأقسام لا تظهر في صفحة الترحيب إلا بعناصر نشطة.
 */

use App\Models\User;
use App\Models\WelcomeMediaItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;


/** صورة وهمية ببايتات PNG حقيقية (بيئة الاختبار بلا مكتبة GD) */
function fakeImageUpload(string $name): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAwMCAO+ip1sAAAAASUVORK5CYII=');
    return UploadedFile::fake()->createWithContent($name, $png);
}

function mediaAdmin(): User
{
    test()->seed(\Database\Seeders\PermissionSeeder::class);
    $admin = User::create(['name' => 'admin', 'email' => 'admin@media.local', 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $admin->assignRole('admin');
    return $admin;
}

test('youtube id extraction from common url shapes', function () {
    expect(WelcomeMediaItem::youtubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
        ->and(WelcomeMediaItem::youtubeId('https://youtu.be/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
        ->and(WelcomeMediaItem::youtubeId('https://www.youtube.com/shorts/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
        ->and(WelcomeMediaItem::youtubeId('https://example.com/video'))->toBeNull()
        ->and(WelcomeMediaItem::youtubeId(null))->toBeNull();
});

test('media admin is admin-only', function () {
    $this->get(route('welcome-media.index'))->assertRedirect(route('login'));

    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $teacher = User::create(['name' => 't', 'email' => 't@media.local', 'password' => Hash::make('Test123!'), 'status' => 'active']);
    $teacher->assignRole('teacher');
    $response = $this->actingAs($teacher)->get(route('welcome-media.index'));
    expect(in_array($response->status(), [302, 403], true))->toBeTrue()
        ->and(WelcomeMediaItem::count())->toBe(0);
});

test('admin stores slider image, video, and rejects invalid input', function () {
    Storage::fake('public');
    $admin = mediaAdmin();

    $this->actingAs($admin)->post(route('welcome-media.store'), [
        'type' => 'image', 'in_slider' => 1, 'title' => 'حفل التكريم', 'sort_order' => 1, 'is_active' => 1,
        'image' => fakeImageUpload('party.jpg'),
    ])->assertRedirect(route('welcome-media.index'));

    $item = WelcomeMediaItem::where('type', 'image')->first();
    expect($item)->not->toBeNull()
        ->and($item->title)->toBe('حفل التكريم')
        ->and(Storage::disk('public')->exists($item->image_path))->toBeTrue();

    $this->actingAs($admin)->post(route('welcome-media.store'), [
        'type' => 'video', 'title' => 'جولة في المركز', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'is_active' => 1,
    ])->assertRedirect(route('welcome-media.index'));
    expect(WelcomeMediaItem::where('type', 'video')->first()?->youtubeIdValue())->toBe('dQw4w9WgXcQ');

    // رابط غير يوتيوب مرفوض، وصورة معرض بلا صورة مرفوضة
    $this->actingAs($admin)->post(route('welcome-media.store'), [
        'type' => 'video', 'video_url' => 'https://example.com/v', 'is_active' => 1,
    ])->assertSessionHasErrors('video_url');
    $this->actingAs($admin)->post(route('welcome-media.store'), [
        'type' => 'image', 'in_gallery' => 1, 'is_active' => 1,
    ])->assertSessionHasErrors('image');

    // صورة بلا أي مكان ظهور مرفوضة
    $this->actingAs($admin)->post(route('welcome-media.store'), [
        'type' => 'image', 'image' => fakeImageUpload('nowhere.jpg'), 'is_active' => 1,
    ])->assertSessionHasErrors('in_slider');
});

test('update replaces the image and deletes the old file; destroy deletes the file', function () {
    Storage::fake('public');
    $admin = mediaAdmin();

    $this->actingAs($admin)->post(route('welcome-media.store'), [
        'type' => 'image', 'in_gallery' => 1, 'title' => 'قديمة', 'image' => fakeImageUpload('old.jpg'), 'is_active' => 1,
    ]);
    $item = WelcomeMediaItem::first();
    $oldPath = $item->image_path;

    $this->actingAs($admin)->put(route('welcome-media.update', $item), [
        'type' => 'image', 'in_gallery' => 1, 'title' => 'جديدة', 'image' => fakeImageUpload('new.jpg'), 'is_active' => 1,
    ])->assertRedirect(route('welcome-media.index'));

    $item->refresh();
    expect($item->title)->toBe('جديدة')
        ->and($item->image_path)->not->toBe($oldPath)
        ->and(Storage::disk('public')->exists($oldPath))->toBeFalse()
        ->and(Storage::disk('public')->exists($item->image_path))->toBeTrue();

    $path = $item->image_path;
    $this->actingAs($admin)->delete(route('welcome-media.destroy', $item))->assertRedirect(route('welcome-media.index'));
    expect(WelcomeMediaItem::count())->toBe(0)
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

test('welcome page renders the three sections only with active items', function () {
    Storage::fake('public');
    $path = fakeImageUpload('s.jpg')->store('welcome-media', 'public');

    // بلا أي عناصر: لا أقسام
    $this->get('/')->assertOk()->assertDontSee('من أجواء المركز')->assertDontSee('فيديوهات المركز');

    WelcomeMediaItem::create(['type' => 'image', 'in_slider' => true, 'title' => 'شريحة ظاهرة', 'image_path' => $path, 'is_active' => true]);
    WelcomeMediaItem::create(['type' => 'image', 'in_gallery' => true, 'title' => 'صورة معرض', 'image_path' => $path, 'is_active' => true]);
    WelcomeMediaItem::create(['type' => 'video', 'title' => 'فيديو ظاهر', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'is_active' => true]);
    WelcomeMediaItem::create(['type' => 'image', 'in_slider' => true, 'title' => 'شريحة مخفية', 'image_path' => $path, 'is_active' => false]);

    $this->get('/')
        ->assertOk()
        ->assertSee('من أجواء المركز')
        ->assertSee('شريحة ظاهرة')
        ->assertSee('معرض الصور')
        ->assertSee('فيديوهات المركز')
        ->assertSee('فيديو ظاهر')
        ->assertDontSee('شريحة مخفية');
});

test('section does not appear at all when the image file is missing', function () {
    Storage::fake('public');

    // عنصر نشط في قاعدة البيانات لكن ملف صورته غير موجود على القرص
    WelcomeMediaItem::create(['type' => 'image', 'in_slider' => true, 'title' => 'صورة مفقودة', 'image_path' => 'welcome-media/missing.jpg', 'is_active' => true]);
    WelcomeMediaItem::create(['type' => 'image', 'in_gallery' => true, 'title' => 'معرض مفقود', 'image_path' => 'welcome-media/missing2.jpg', 'is_active' => true]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('من أجواء المركز')
        ->assertDontSee('معرض الصور')
        ->assertDontSee('صورة مفقودة');

    // وبمجرد وجود ملف حقيقي يظهر القسم
    $path = fakeImageUpload('real.jpg')->store('welcome-media', 'public');
    WelcomeMediaItem::create(['type' => 'image', 'in_slider' => true, 'title' => 'صورة حقيقية', 'image_path' => $path, 'is_active' => true]);

    $this->get('/')->assertOk()->assertSee('من أجواء المركز')->assertSee('صورة حقيقية');
});

test('one uploaded image can appear in both the slider and the gallery', function () {
    Storage::fake('public');
    $admin = mediaAdmin();

    $this->actingAs($admin)->post(route('welcome-media.store'), [
        'type' => 'image', 'in_slider' => 1, 'in_gallery' => 1,
        'title' => 'صورة مشتركة', 'image' => fakeImageUpload('shared.jpg'), 'is_active' => 1,
    ])->assertRedirect(route('welcome-media.index'));

    expect(WelcomeMediaItem::count())->toBe(1);

    $page = $this->get('/')->assertOk();
    $page->assertSee('من أجواء المركز')->assertSee('معرض الصور');
    expect(substr_count($page->getContent(), 'صورة مشتركة'))->toBeGreaterThanOrEqual(2);
});
