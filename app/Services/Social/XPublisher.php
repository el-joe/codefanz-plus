<?php

namespace App\Services\Social;

use App\Exceptions\SocialApiException;
use App\Models\SocialAccount;
use App\Models\SocialPostVariant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * X (API v2, OAuth 2.0 user context). Images go through the v2 media upload endpoint, then one post is created.
 * Posting needs an X API plan that includes write access; a 403 for the plan is reported as "needs app approval".
 */
class XPublisher extends HttpSocialPublisher
{
    public function publish(SocialPostVariant $variant, SocialAccount $account): PublishResult
    {
        $post = $variant->post;
        $mediaIds = [];

        foreach ($variant->mediaItems($post) as $media) {
            $cached = $this->remembered($variant, 'media.m'.$media->id);

            if (! is_string($cached) || $cached === '') {
                $cached = (string) $this->call(fn () => $this->api($account)
                    ->attach('media', $this->mediaContents($media), $media->original_name, ['Content-Type' => $media->mime_type])
                    ->post($this->url('/2/media/upload'), ['media_category' => 'tweet_image', 'media_type' => $media->mime_type]), __('X media upload'))->json('data.id');

                if ($cached === '') {
                    throw new SocialApiException(__('X returned no media id.'));
                }

                $this->remember($variant, 'media.m'.$media->id, $cached);

                $alt = $this->altText($variant, $media);

                if ($alt !== '') {
                    try {
                        $this->call(fn () => $this->api($account)->post($this->url('/2/media/metadata'), [
                            'id' => $cached,
                            'metadata' => ['alt_text' => ['text' => mb_substr($alt, 0, 1000)]],
                        ]), __('X alt text'));
                    } catch (SocialApiException) {
                        // Alt text is best effort; the image itself is uploaded.
                    }
                }
            }

            $mediaIds[] = $cached;
        }

        $body = ['text' => $variant->composedText($post)];

        if ($mediaIds !== []) {
            $body['media'] = ['media_ids' => $mediaIds];
        }

        $id = (string) $this->call(fn () => $this->api($account)->post($this->url('/2/tweets'), $body), __('X post'))->json('data.id');

        if ($id === '') {
            throw new SocialApiException(__('X accepted the post but returned no id.'), SocialApiException::UNKNOWN);
        }

        $result = ['id' => $id];

        if (filled($variant->first_comment)) {
            try {
                $result['first_comment'] = ['id' => (string) $this->call(fn () => $this->api($account)->post($this->url('/2/tweets'), [
                    'text' => (string) $variant->first_comment,
                    'reply' => ['in_reply_to_tweet_id' => $id],
                ]), __('X reply'))->json('data.id')];
            } catch (SocialApiException $exception) {
                $result['first_comment'] = ['error' => $exception->getMessage()];
            }
        }

        $handle = ltrim((string) $account->handle, '@');

        return new PublishResult($id, 'https://x.com/'.($handle !== '' ? $handle : 'i/web').'/status/'.$id, $result);
    }

    public function metrics(SocialPostVariant $variant, SocialAccount $account): array
    {
        $response = $this->call(fn () => $this->api($account)->get($this->url('/2/tweets/'.$variant->external_id), [
            'tweet.fields' => 'public_metrics,non_public_metrics',
        ]), __('X metrics'));

        $public = (array) $response->json('data.public_metrics', []);
        $private = (array) $response->json('data.non_public_metrics', []);
        $engagementKeys = ['like_count', 'reply_count', 'retweet_count', 'quote_count'];

        return [
            'impressions' => isset($public['impression_count']) ? (int) $public['impression_count'] : null,
            'engagements' => collect($engagementKeys)->every(fn (string $key): bool => isset($public[$key])) ? (int) collect($engagementKeys)->sum(fn (string $key): int => (int) $public[$key]) : null,
            'clicks' => isset($private['url_link_clicks']) ? (int) $private['url_link_clicks'] : null,
            'raw' => ['public_metrics' => $public, 'non_public_metrics' => $private],
        ];
    }

    public function verify(SocialAccount $account): void
    {
        $this->call(fn () => $this->api($account)->get($this->url('/2/users/me')), __('X profile'));
    }

    protected function classify(Response $response, string $what): SocialApiException
    {
        $exception = parent::classify($response, $what);

        // A duplicate post is a content problem, not a missing permission.
        if ($response->status() === 403 && str_contains(strtolower($response->body()), 'duplicate')) {
            return new SocialApiException($exception->getMessage(), SocialApiException::PERMANENT, 403);
        }

        return $exception;
    }

    private function api(SocialAccount $account): PendingRequest
    {
        return $this->http()->withToken((string) $account->access_token);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('codefanz.social.providers.x.api_url'), '/').$path;
    }
}
