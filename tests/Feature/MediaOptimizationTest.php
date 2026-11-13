<?php

use App\Enums\MediaStatus;
use App\Jobs\OptimizeImage;
use App\Models\Media;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function storeOriginal(string $bytes, string $extension): Media
{
    $media = Media::factory()->create(['status' => MediaStatus::Processing, 'width' => null, 'height' => null]);
    $media->update(['original_path' => "media/originals/{$media->uuid}.{$extension}"]);
    Storage::disk('local')->put($media->original_path, $bytes);

    return $media;
}

function jpegBytes(int $width, int $height): string
{
    $image = new Imagick;
    $image->newImage($width, $height, new ImagickPixel('#5a3df5'));
    $image->setImageFormat('jpeg');

    return $image->getImagesBlob();
}

test('a photo is converted to responsive avif and webp variants', function () {
    $media = storeOriginal(jpegBytes(1000, 500), 'jpg');

    (new OptimizeImage($media))->handle();

    $media->refresh()->load('variants');

    expect($media->status)->toBe(MediaStatus::Ready)
        ->and($media->width)->toBe(1000)
        ->and($media->variantsFor('avif')->pluck('width')->all())->toBe([320, 640, 960, 1000])
        ->and($media->variantsFor('webp')->pluck('width')->all())->toBe([320, 640, 960, 1000])
        ->and($media->largestVariant('webp')->height)->toBe(500);

    $avif = Storage::disk('public')->get("media/{$media->uuid}/1000.avif");
    $webp = Storage::disk('public')->get("media/{$media->uuid}/320.webp");

    expect(substr($avif, 4, 8))->toBe('ftypavif')
        ->and(substr($webp, 8, 4))->toBe('WEBP');
});

test('images are never upscaled beyond the largest configured width', function () {
    $media = storeOriginal(jpegBytes(2400, 600), 'jpg');

    (new OptimizeImage($media))->handle();

    expect($media->fresh()->variants()->max('width'))->toBe(1920);
});

test('animated gifs are kept as a single animated variant', function () {
    $gif = new Imagick;
    foreach (['#5a3df5', '#1ad5c8'] as $color) {
        $frame = new Imagick;
        $frame->newImage(40, 40, new ImagickPixel($color));
        $frame->setImageFormat('gif');
        $frame->setImageDelay(20);
        $gif->addImage($frame);
    }
    $gif->setFormat('gif');

    $media = storeOriginal($gif->getImagesBlob(), 'gif');

    (new OptimizeImage($media))->handle();

    $media->refresh();

    expect($media->is_animated)->toBeTrue()
        ->and($media->variants()->pluck('format')->all())->toBe(['gif']);
});

test('a failed optimization is recorded on the media item', function () {
    $media = storeOriginal('not an image', 'jpg');

    $job = new OptimizeImage($media);

    try {
        $job->handle();
    } catch (Throwable $exception) {
        $job->failed($exception);
    }

    expect($media->fresh()->status)->toBe(MediaStatus::Failed)
        ->and($media->fresh()->error)->not->toBeEmpty();
});

test('the picture component outputs avif before webp with dimensions and localized alt', function () {
    $media = storeOriginal(jpegBytes(800, 400), 'jpg');
    (new OptimizeImage($media))->handle();
    $media->refresh()->update(['alt' => ['en' => 'Our office', 'ar' => 'مكتبنا']]);

    app()->setLocale('ar');
    $html = Blade::render('<x-media.picture :media="$media" sizes="50vw" />', ['media' => $media->load('variants')]);

    expect($html)->toContain('type="image/avif"')
        ->and(strpos($html, 'image/avif'))->toBeLessThan(strpos($html, 'image/webp'))
        ->and($html)->toContain('width="800" height="400"')
        ->and($html)->toContain('alt="مكتبنا"')
        ->and($html)->toContain('loading="lazy"')
        ->and($html)->not->toContain('fetchpriority')
        ->and($html)->toContain('sizes="50vw"');

    // Above-the-fold images (LCP candidates) load eagerly with high fetch priority.
    $eager = Blade::render('<x-media.picture :media="$media" :eager="true" />', ['media' => $media]);
    expect($eager)->toContain('loading="eager"')->toContain('fetchpriority="high"');
});
