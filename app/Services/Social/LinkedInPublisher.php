<?php

namespace App\Services\Social;

use App\Exceptions\SocialApiException;
use App\Models\Media;
use App\Models\SocialAccount;
use App\Models\SocialPostVariant;
use Illuminate\Http\Client\PendingRequest;

/**
 * LinkedIn Pages through the Community Management API (versioned REST Posts API). Images are uploaded with the
 * Images API first; the link goes out as an article attachment; hashtags use LinkedIn's hashtag markup.
 */
class LinkedInPublisher extends HttpSocialPublisher
{
    public function publish(SocialPostVariant $variant, SocialAccount $account): PublishResult
    {
        $post = $variant->post;
        $author = $this->organizationUrn($account);
        $body = [
            'author' => $author,
            'commentary' => self::littleText($variant->composedText($post)),
            'visibility' => 'PUBLIC',
            'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        $images = [];

        foreach ($variant->mediaItems($post) as $media) {
            $images[] = ['id' => $this->uploadImage($variant, $account, $media), 'altText' => mb_substr($this->altText($variant, $media), 0, 300)];
        }

        $link = $post->trackedLink($variant->platform->utmSource());

        if (count($images) === 1) {
            $body['content'] = ['media' => $images[0]];
        } elseif (count($images) > 1) {
            $body['content'] = ['multiImage' => ['images' => $images]];
        } elseif ($link !== null) {
            $body['content'] = ['article' => ['source' => $link, 'title' => mb_substr($post->title, 0, 200)]];
        }

        $response = $this->call(fn () => $this->api($account)->post($this->url('/rest/posts'), $body), __('LinkedIn post'));
        $urn = (string) ($response->header('x-restli-id') ?: $response->json('id', ''));

        if ($urn === '') {
            throw new SocialApiException(__('LinkedIn accepted the post but returned no id.'), SocialApiException::UNKNOWN);
        }

        $result = ['id' => $urn];

        if (filled($variant->first_comment)) {
            $result['first_comment'] = $this->comment($account, $author, $urn, (string) $variant->first_comment);
        }

        return new PublishResult($urn, 'https://www.linkedin.com/feed/update/'.$urn.'/', $result);
    }

    public function metrics(SocialPostVariant $variant, SocialAccount $account): array
    {
        $urn = (string) $variant->external_id;
        $list = str_starts_with($urn, 'urn:li:ugcPost:') ? 'ugcPosts' : 'shares';
        $query = 'q=organizationalEntity&organizationalEntity='.rawurlencode($this->organizationUrn($account)).'&'.$list.'=List('.rawurlencode($urn).')';

        $response = $this->call(fn () => $this->api($account)->get($this->url('/rest/organizationalEntityShareStatistics').'?'.$query), __('LinkedIn statistics'));
        $stats = $response->json('elements.0.totalShareStatistics');

        if (! is_array($stats)) {
            return ['impressions' => null, 'engagements' => null, 'clicks' => null, 'raw' => []];
        }

        $engagements = collect(['likeCount', 'commentCount', 'shareCount'])->every(fn (string $key): bool => isset($stats[$key]))
            ? (int) $stats['likeCount'] + (int) $stats['commentCount'] + (int) $stats['shareCount']
            : null;

        return [
            'impressions' => isset($stats['impressionCount']) ? (int) $stats['impressionCount'] : null,
            'engagements' => $engagements,
            'clicks' => isset($stats['clickCount']) ? (int) $stats['clickCount'] : null,
            'raw' => array_intersect_key($stats, array_flip(['impressionCount', 'uniqueImpressionsCount', 'clickCount', 'likeCount', 'commentCount', 'shareCount', 'engagement'])),
        ];
    }

    public function verify(SocialAccount $account): void
    {
        $this->call(fn () => $this->api($account)->get($this->url('/rest/organizations/'.rawurlencode((string) $account->external_id))), __('LinkedIn organization'));
    }

    /**
     * LinkedIn "little text": reserved characters are escaped and #words become hashtag elements.
     */
    public static function littleText(string $text): string
    {
        $escaped = (string) preg_replace('/([\\\\|{}@\[\]()<>#*_~])/u', '\\\\$1', $text);

        return (string) preg_replace('/\\\\#([\p{L}\p{N}_]+)/u', '{hashtag|\\\\#|$1}', $escaped);
    }

    public function api(SocialAccount $account): PendingRequest
    {
        return $this->http()
            ->withToken((string) $account->access_token)
            ->withHeaders([
                'LinkedIn-Version' => (string) config('codefanz.social.providers.linkedin.version'),
                'X-Restli-Protocol-Version' => '2.0.0',
            ]);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('codefanz.social.providers.linkedin.api_url'), '/').$path;
    }

    private function organizationUrn(SocialAccount $account): string
    {
        return 'urn:li:organization:'.$account->external_id;
    }

    private function uploadImage(SocialPostVariant $variant, SocialAccount $account, Media $media): string
    {
        $cached = $this->remembered($variant, 'images.m'.$media->id);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $init = $this->call(fn () => $this->api($account)->post($this->url('/rest/images?action=initializeUpload'), [
            'initializeUploadRequest' => ['owner' => $this->organizationUrn($account)],
        ]), __('LinkedIn image upload'));

        $uploadUrl = (string) $init->json('value.uploadUrl');
        $imageUrn = (string) $init->json('value.image');

        if ($uploadUrl === '' || $imageUrn === '') {
            throw new SocialApiException(__('LinkedIn did not return an image upload URL.'));
        }

        $this->call(fn () => $this->http()->withToken((string) $account->access_token)
            ->withBody($this->mediaContents($media), $media->mime_type)
            ->put($uploadUrl), __('LinkedIn image upload'));

        $this->remember($variant, 'images.m'.$media->id, $imageUrn);

        return $imageUrn;
    }

    /**
     * The first comment is best effort: the post is already live, so a failure is recorded, not retried.
     *
     * @return array{id?: string, error?: string}
     */
    private function comment(SocialAccount $account, string $author, string $urn, string $text): array
    {
        try {
            $response = $this->call(fn () => $this->api($account)->post($this->url('/rest/socialActions/'.rawurlencode($urn).'/comments'), [
                'actor' => $author,
                'object' => $urn,
                'message' => ['text' => $text],
            ]), __('LinkedIn first comment'));

            return ['id' => (string) ($response->header('x-restli-id') ?: $response->json('id', ''))];
        } catch (SocialApiException $exception) {
            return ['error' => $exception->getMessage()];
        }
    }
}
