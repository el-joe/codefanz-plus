<?php

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;
use Throwable;

/**
 * Generates responsive AVIF (preferred) and WebP (fallback) variants. Animated images are kept as-is.
 */
class OptimizeImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public Media $media)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $source = Storage::disk($this->media->original_disk)->get($this->media->original_path)
            ?? throw new RuntimeException('Original file is missing.');

        $manager = ImageManager::usingDriver(Driver::class);
        $probe = $manager->decode($source)->orient();
        $isAnimated = $probe->isAnimated();
        $originalWidth = $probe->width();
        $originalHeight = $probe->height();

        $this->media->variants()->delete();

        if ($isAnimated) {
            $this->storeVariant($source, 'gif', $originalWidth, $originalHeight);
        } else {
            $config = config('codefanz.media');
            $widths = array_values(array_filter($config['widths'], fn (int $width): bool => $width < $originalWidth));
            $widths[] = min($originalWidth, max($config['widths']));

            foreach (array_unique($widths) as $width) {
                foreach (['avif' => new AvifEncoder(quality: $config['quality']['avif'], strip: false), 'webp' => new WebpEncoder(quality: $config['quality']['webp'], strip: false)] as $format => $encoder) {
                    $image = $this->prepare($manager->decode($source))->scaleDown(width: $width);
                    $encoded = (string) $image->encode($encoder);

                    $this->assertFormat($encoded, $format);
                    $this->storeVariant($encoded, $format, $image->width(), $image->height());
                }
            }
        }

        $this->media->forceFill([
            'width' => $originalWidth,
            'height' => $originalHeight,
            'is_animated' => $isAnimated,
            'status' => MediaStatus::Ready,
            'error' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $this->media->forceFill([
            'status' => MediaStatus::Failed,
            'error' => mb_substr($exception?->getMessage() ?? 'Unknown error', 0, 255),
        ])->save();
    }

    /**
     * Auto-orient and strip EXIF/XMP/IPTC metadata while keeping the ICC colour profile.
     */
    private function prepare(ImageInterface $image): ImageInterface
    {
        $image->orient();
        $native = $image->core()->native();

        if ($native instanceof \Imagick) {
            foreach (array_keys($native->getImageProfiles('*', false)) as $profile) {
                if (strtolower((string) $profile) !== 'icc') {
                    $native->removeImageProfile((string) $profile);
                }
            }
        }

        return $image;
    }

    private function assertFormat(string $bytes, string $format): void
    {
        $valid = match ($format) {
            'avif' => substr($bytes, 4, 4) === 'ftyp' && in_array(substr($bytes, 8, 4), ['avif', 'avis'], true),
            'webp' => str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP',
            default => false,
        };

        if (! $valid) {
            throw new RuntimeException("Encoder did not produce a valid {$format} file.");
        }
    }

    private function storeVariant(string $bytes, string $format, int $width, int $height): void
    {
        $disk = config('codefanz.media.public_disk');
        $path = "media/{$this->media->uuid}/{$width}.{$format}";

        Storage::disk($disk)->put($path, $bytes);

        $this->media->variants()->create([
            'format' => $format,
            'width' => $width,
            'height' => $height,
            'disk' => $disk,
            'path' => $path,
            'size' => strlen($bytes),
        ]);
    }
}
