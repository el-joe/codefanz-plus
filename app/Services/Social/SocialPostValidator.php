<?php

namespace App\Services\Social;

use App\Enums\SocialPlatform;
use App\Models\Media;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use Illuminate\Support\Collection;

/**
 * Platform rules for post variants: composed length (caption + hashtags + link as the platform counts it), hashtag
 * count, first-comment support, and media count / type / aspect ratio. Used when saving (limits) and again before
 * review and approval (limits + required text and media), so AI-filled copy cannot slip past.
 */
class SocialPostValidator
{
    /**
     * @param  array<int|string, array{platform: SocialPlatform, text: string, hashtags: string, first_comment: string, include_media: bool}>  $variants  keyed by account id
     * @param  Collection<int, Media>  $media
     * @return array<string, string> field => message
     */
    public function errors(array $variants, Collection $media, ?string $trackedLinkBase, bool $complete): array
    {
        $errors = [];

        if ($complete && $variants === []) {
            $errors['variants'] = __('Choose at least one account to publish to.');
        }

        foreach ($variants as $accountId => $variant) {
            $platform = $variant['platform'];
            $field = 'variants.'.$accountId;
            $link = $trackedLinkBase !== null ? $trackedLinkBase.'?utm_source='.$platform->utmSource() : null;
            $composed = SocialPostVariant::compose($platform, $variant['text'], $variant['hashtags'], $link);
            $length = SocialPostVariant::measure($platform, $composed);

            if ($complete && trim($variant['text']) === '') {
                $errors[$field.'.text'] = __('Write the :platform text.', ['platform' => $platform->label()]);
            } elseif ($length > $platform->maxLength()) {
                $errors[$field.'.text'] = __(':platform allows :max characters including hashtags and link; this post has :count.', ['platform' => $platform->label(), 'max' => number_format($platform->maxLength()), 'count' => number_format($length)]);
            }

            $tags = count(SocialPostVariant::parseHashtags($variant['hashtags']));

            if ($platform->maxHashtags() !== null && $tags > $platform->maxHashtags()) {
                $errors[$field.'.hashtags'] = __(':platform allows at most :max hashtags.', ['platform' => $platform->label(), 'max' => $platform->maxHashtags()]);
            }

            if (filled($variant['first_comment']) && ! $platform->supportsFirstComment()) {
                $errors[$field.'.first_comment'] = __(':platform does not support a first comment.', ['platform' => $platform->label()]);
            }

            $items = $variant['include_media'] ? $media : collect();

            if ($complete && $platform->requiresMedia() && $items->isEmpty()) {
                $errors[$field.'.include_media'] = __(':platform posts need at least one image or video.', ['platform' => $platform->label()]);
            }

            if ($items->count() > $platform->maxMedia()) {
                $errors[$field.'.include_media'] = __(':platform allows at most :max images.', ['platform' => $platform->label(), 'max' => $platform->maxMedia()]);
            }

            $mimes = $platform->allowedMimeTypes();
            $range = $platform->aspectRatioRange();

            foreach ($items as $item) {
                if ($mimes !== null && ! in_array($item->mime_type, $mimes, true)) {
                    $errors[$field.'.include_media'] = __(':platform does not accept “:name” (:type).', ['platform' => $platform->label(), 'name' => $item->original_name, 'type' => $item->mime_type]);
                    break;
                }

                if ($range !== null && $item->width && $item->height) {
                    $ratio = $item->width / $item->height;

                    if ($ratio < $range['min'] - 0.005 || $ratio > $range['max'] + 0.005) {
                        $errors[$field.'.include_media'] = __('“:name” is :ratio:1; :platform needs between 4:5 and 1.91:1.', ['name' => $item->original_name, 'ratio' => number_format($ratio, 2), 'platform' => $platform->label()]);
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public function errorsFor(SocialPost $post, bool $complete = true): array
    {
        $post->loadMissing('variants');
        $variants = [];

        foreach ($post->variants as $variant) {
            $variants[$variant->social_account_id] = [
                'platform' => $variant->platform,
                'text' => (string) $variant->text,
                'hashtags' => (string) $variant->hashtags,
                'first_comment' => (string) $variant->first_comment,
                'include_media' => $variant->include_media,
            ];
        }

        return $this->errors($variants, $post->mediaIn(SocialPost::MEDIA_COLLECTION)->get(), self::linkBase($post->link_url, $post->utm ?? []), $complete);
    }

    /**
     * A stand-in for the tracked link with the right length class (UTM parameters included) for counting.
     *
     * @param  array<string, mixed>  $utm
     */
    public static function linkBase(?string $link, array $utm): ?string
    {
        if (blank($link)) {
            return null;
        }

        $extra = collect(['medium', 'campaign', 'content'])->filter(fn (string $key): bool => filled($utm[$key] ?? null))
            ->map(fn (string $key): string => '&utm_'.$key.'='.rawurlencode((string) $utm[$key]))->implode('');

        return $link.$extra;
    }
}
