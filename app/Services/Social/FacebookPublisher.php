<?php

namespace App\Services\Social;

use App\Exceptions\SocialApiException;
use App\Models\SocialAccount;
use App\Models\SocialPostVariant;

/**
 * Facebook Pages through the Graph API with the page access token. Images are uploaded unpublished (fetched by
 * Meta from a signed, expiring URL) and attached to one feed post; without images the link is a link attachment.
 */
class FacebookPublisher extends MetaPublisher
{
    public function publish(SocialPostVariant $variant, SocialAccount $account): PublishResult
    {
        $post = $variant->post;
        $page = (string) $account->external_id;
        $token = (string) $account->access_token;
        $message = $variant->composedText($post);
        $link = $post->trackedLink($variant->platform->utmSource());
        $photos = [];

        foreach ($variant->mediaItems($post) as $media) {
            $cached = $this->remembered($variant, 'photos.m'.$media->id);

            if (! is_string($cached) || $cached === '') {
                $response = $this->call(fn () => $this->http()->asForm()->post($this->graph($page.'/photos'), [
                    'url' => $this->mediaUrl($media),
                    'published' => 'false',
                    'alt_text_custom' => $this->altText($variant, $media),
                    'access_token' => $token,
                ]), __('Facebook photo upload'));
                $cached = (string) $response->json('id');
                $this->remember($variant, 'photos.m'.$media->id, $cached);
            }

            $photos[] = ['media_fbid' => $cached];
        }

        $fields = ['message' => $message, 'access_token' => $token];

        if ($photos !== []) {
            // Link attachments are ignored when photos are attached, so the link goes into the text.
            $fields['message'] = $link !== null ? $message."\n\n".$link : $message;

            foreach ($photos as $index => $photo) {
                $fields['attached_media['.$index.']'] = json_encode($photo);
            }
        } elseif ($link !== null) {
            $fields['link'] = $link;
        }

        $response = $this->call(fn () => $this->http()->asForm()->post($this->graph($page.'/feed'), $fields), __('Facebook post'));
        $id = (string) $response->json('id');
        $result = ['id' => $id];

        if (filled($variant->first_comment)) {
            $result['first_comment'] = $this->bestEffort(fn () => (string) $this->call(fn () => $this->http()->asForm()->post($this->graph($id.'/comments'), [
                'message' => (string) $variant->first_comment,
                'access_token' => $token,
            ]), __('Facebook first comment'))->json('id'));
        }

        return new PublishResult($id, 'https://www.facebook.com/'.$id, $result);
    }

    public function metrics(SocialPostVariant $variant, SocialAccount $account): array
    {
        $id = (string) $variant->external_id;
        $token = (string) $account->access_token;

        $insights = collect($this->call(fn () => $this->http()->get($this->graph($id.'/insights'), [
            'metric' => 'post_impressions,post_clicks',
            'access_token' => $token,
        ]), __('Facebook insights'))->json('data', []))->mapWithKeys(fn (array $row): array => [$row['name'] ?? '' => $row['values'][0]['value'] ?? null]);

        $counts = $this->call(fn () => $this->http()->get($this->graph($id), [
            'fields' => 'reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0),shares',
            'access_token' => $token,
        ]), __('Facebook post counts'))->json();

        $reactions = data_get($counts, 'reactions.summary.total_count');
        $comments = data_get($counts, 'comments.summary.total_count');

        return [
            'impressions' => is_numeric($insights['post_impressions'] ?? null) ? (int) $insights['post_impressions'] : null,
            'engagements' => is_numeric($reactions) && is_numeric($comments) ? (int) $reactions + (int) $comments + (int) data_get($counts, 'shares.count', 0) : null,
            'clicks' => is_numeric($insights['post_clicks'] ?? null) ? (int) $insights['post_clicks'] : null,
            'raw' => ['insights' => $insights->all(), 'reactions' => $reactions, 'comments' => $comments, 'shares' => data_get($counts, 'shares.count')],
        ];
    }

    public function verify(SocialAccount $account): void
    {
        $this->call(fn () => $this->http()->get($this->graph((string) $account->external_id), [
            'fields' => 'id,name',
            'access_token' => (string) $account->access_token,
        ]), __('Facebook page'));
    }

    /**
     * @param  callable(): string  $call
     * @return array{id?: string, error?: string}
     */
    protected function bestEffort(callable $call): array
    {
        try {
            return ['id' => $call()];
        } catch (SocialApiException $exception) {
            return ['error' => $exception->getMessage()];
        }
    }
}
