<?php

use App\Enums\MediaStatus;
use App\Enums\Role;
use App\Jobs\OptimizeImage;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Mediable;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();

    $this->editor = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create();
});

test('staff without media permission cannot open the library', function () {
    $this->actingAs(User::factory()->role(Role::SupportAgent)->create())
        ->get(route('admin.media.index'))
        ->assertForbidden();
});

test('an editor uploads images that are stored privately and queued for optimization', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.media.store'), [
            'files' => [UploadedFile::fake()->image('hero.jpg', 1200, 800), UploadedFile::fake()->image('team.png', 640, 480)],
        ])
        ->assertRedirect(route('admin.media.index'))
        ->assertSessionHas('toast');

    $media = Media::query()->where('original_name', 'hero.jpg')->sole();

    expect(Media::count())->toBe(2)
        ->and($media->status)->toBe(MediaStatus::Processing)
        ->and($media->width)->toBe(1200)
        ->and($media->uploaded_by)->toBe($this->editor->id)
        ->and(AuditLog::query()->where('operation', 'media.uploaded')->count())->toBe(2);

    Storage::disk('local')->assertExists($media->original_path);
    Storage::disk('public')->assertMissing($media->original_path);
    Queue::assertPushedOn('media', OptimizeImage::class);
    Queue::assertPushed(OptimizeImage::class, 2);
});

test('uploading the same file twice stores it once', function () {
    $file = UploadedFile::fake()->image('logo.png', 300, 300);

    $this->actingAs($this->editor)->post(route('admin.media.store'), ['files' => [$file]]);
    $this->actingAs($this->editor)->post(route('admin.media.store'), ['files' => [$file]]);

    expect(Media::count())->toBe(1);
    Queue::assertPushed(OptimizeImage::class, 1);
});

test('uploads that are not allowed images are rejected', function (UploadedFile $file) {
    $this->actingAs($this->editor)
        ->post(route('admin.media.store'), ['files' => [$file]])
        ->assertSessionHasErrors();

    expect(Media::count())->toBe(0);
})->with([
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'pdf' => fn () => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
    'php disguised as jpg' => fn () => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo 1;'),
    'oversized dimensions' => fn () => UploadedFile::fake()->image('huge.png', 8100, 10),
]);

test('an editor updates alt text per language', function () {
    $media = Media::factory()->create();

    $this->actingAs($this->editor)
        ->patch(route('admin.media.update', $media), ['alt' => ['en' => 'Team at work', 'ar' => 'الفريق أثناء العمل'], 'folder' => 'about'])
        ->assertSessionHasNoErrors();

    $media->refresh();

    expect($media->altText('ar'))->toBe('الفريق أثناء العمل')
        ->and($media->folder)->toBe('about');
});

test('default-language alt text is required', function () {
    $media = Media::factory()->create();

    $this->actingAs($this->editor)
        ->patch(route('admin.media.update', $media), ['alt' => ['en' => '', 'ar' => 'نص']])
        ->assertSessionHasErrors('alt.en');
});

test('a file in use cannot be deleted', function () {
    $media = Media::factory()->create();
    Mediable::create(['media_id' => $media->id, 'mediable_type' => 'page', 'mediable_id' => Page::factory()->create()->id, 'collection' => 'cover']);

    $this->actingAs($this->editor)
        ->delete(route('admin.media.destroy', $media))
        ->assertSessionHasErrors('media');

    expect($media->fresh()->trashed())->toBeFalse();
});

test('an unused file is moved to trash', function () {
    $media = Media::factory()->create();

    $this->actingAs($this->editor)
        ->delete(route('admin.media.destroy', $media))
        ->assertRedirect(route('admin.media.index'));

    expect($media->fresh()->trashed())->toBeTrue();
});

test('the library lists files and the detail page renders', function () {
    $media = Media::factory()->create(['original_name' => 'office-photo.jpg']);

    $this->actingAs($this->editor)->get(route('admin.media.index'))->assertSuccessful()->assertSee('office-photo.jpg');
    $this->actingAs($this->editor)->get(route('admin.media.show', $media))->assertSuccessful()->assertSee('office-photo.jpg');
});
