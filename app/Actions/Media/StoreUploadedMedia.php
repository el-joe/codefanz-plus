<?php

namespace App\Actions\Media;

use App\Enums\MediaStatus;
use App\Jobs\OptimizeImage;
use App\Models\Media;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores the original privately and queues optimization. Identical files are not stored twice.
 */
class StoreUploadedMedia
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(UploadedFile $file, User $user, ?string $folder = null): Media
    {
        $checksum = hash_file('sha256', $file->getRealPath());

        $existing = Media::query()->where('checksum', $checksum)->first();

        if ($existing !== null) {
            return $existing;
        }

        $uuid = (string) Str::uuid();
        $extension = $file->guessExtension() ?? 'bin';
        $disk = config('codefanz.media.private_disk');
        $path = $file->storeAs('media/originals', "{$uuid}.{$extension}", $disk);
        [$width, $height] = getimagesize($file->getRealPath()) ?: [null, null];

        return DB::transaction(function () use ($file, $user, $folder, $checksum, $uuid, $disk, $path, $width, $height): Media {
            $media = Media::create([
                'uuid' => $uuid,
                'original_disk' => $disk,
                'original_path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'checksum' => $checksum,
                'width' => $width,
                'height' => $height,
                'folder' => $folder,
                'status' => MediaStatus::Processing,
                'uploaded_by' => $user->getKey(),
            ]);

            OptimizeImage::dispatch($media)->afterCommit();

            $this->audit->record(operation: 'media.uploaded', auditable: $media, user: $user, after: [
                'name' => $media->original_name,
                'size' => $media->size,
            ]);

            return $media;
        });
    }
}
