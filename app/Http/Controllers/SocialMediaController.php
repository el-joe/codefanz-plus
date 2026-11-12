<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the original of an image attached to a social post through a temporary signed URL, so Meta can fetch it
 * while publishing. Images not attached to any social post are never served here.
 */
class SocialMediaController extends Controller
{
    public function __invoke(string $media): StreamedResponse
    {
        // This route runs without the web middleware group, so the model is resolved here.
        $media = Media::query()->where('uuid', $media)->firstOrFail();
        $attached = $media->usages()->where('mediable_type', (new SocialPost)->getMorphClass())->exists();

        abort_unless($attached && str_starts_with((string) $media->mime_type, 'image/'), 404);
        abort_unless(Storage::disk($media->original_disk)->exists($media->original_path), 404);

        return Storage::disk($media->original_disk)->response($media->original_path, $media->original_name, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, max-age=600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
