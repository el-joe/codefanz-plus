<?php

namespace App\Console\Commands;

use App\Services\SiteImages;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageInterface;

#[Signature('site-images:build {--output= : Directory to write into (defaults to public/images/v2)}')]
#[Description('Generate the responsive AVIF/WebP variants and JPEG fallbacks of the Design v2 site images')]
class BuildSiteImagesCommand extends Command
{
    public function handle(): int
    {
        $output = $this->option('output') ?: public_path(SiteImages::PUBLIC_DIRECTORY);
        File::ensureDirectoryExists($output);

        $manager = ImageManager::usingDriver(Driver::class);
        $quality = config('codefanz.media.quality');
        $rows = [];

        foreach (SiteImages::definitions() as $name => $definition) {
            $source = base_path(SiteImages::SOURCE_DIRECTORY."/{$name}.jpg");

            if (! File::exists($source)) {
                $this->error("Missing source image {$source}.");

                return self::FAILURE;
            }

            $bytes = File::get($source);
            $fallbackWidth = SiteImages::fallbackWidth($name);

            foreach (SiteImages::widths($name) as $width) {
                $encoders = [
                    'avif' => new AvifEncoder(quality: $quality['avif'], strip: true),
                    'webp' => new WebpEncoder(quality: $quality['webp'], strip: true),
                ];

                if ($width === $fallbackWidth) {
                    $encoders['jpg'] = new JpegEncoder(quality: 78, progressive: true, strip: true);
                }

                foreach ($encoders as $format => $encoder) {
                    $size = $this->write($manager->decode($bytes), $encoder, $width, SiteImages::heightFor($name, $width), "{$output}/{$name}-{$width}.{$format}");
                    $rows[] = ["{$name}-{$width}.{$format}", number_format($size / 1024, 1).' KB'];
                }
            }
        }

        $hero = File::get(base_path(SiteImages::SOURCE_DIRECTORY.'/hero-glass-shapes.jpg'));
        $size = $this->write($manager->decode($hero), new JpegEncoder(quality: 80, progressive: true, strip: true), 1200, 630, $output.'/'.SiteImages::OG_IMAGE);
        $rows[] = [SiteImages::OG_IMAGE, number_format($size / 1024, 1).' KB'];

        $this->table(['File', 'Size'], $rows);
        $this->info(count($rows).' files written to '.$output.'.');

        return self::SUCCESS;
    }

    /**
     * Centre-crop to the target aspect ratio, resize and encode. Returns the file size in bytes.
     */
    private function write(ImageInterface $image, EncoderInterface $encoder, int $width, int $height, string $path): int
    {
        $encoded = (string) $image->orient()->cover($width, $height)->encode($encoder);
        File::put($path, $encoded);

        return strlen($encoded);
    }
}
